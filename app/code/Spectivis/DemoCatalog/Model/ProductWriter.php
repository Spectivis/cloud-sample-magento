<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Writes products from CatalogPlan straight to Magento's tables, many rows
 * per statement, the way Magento's own importer and performance fixtures do:
 * the product, its attribute values, website, category, legacy stock and
 * the default inventory source, the shared image, the URL rewrite, the
 * configurable product's options and children, and related products.
 *
 * Values are written at the default (admin) scope. Indexes are left to a
 * full reindex once the catalog is written.
 */
class ProductWriter
{
    private const INSERT_ROWS = 2000;

    /** @var array<string, array{id: int, table: string}> */
    private array $attributes = [];

    /** @var array<string, array<string, int>> attribute code => option label => option id */
    private array $options = [];

    private int $attributeSetId = 0;

    /** @var list<int> leaf index => category id */
    private array $categoryIds = [];

    /** @var array<string, int> image file => media gallery value id */
    private array $images = [];

    private int $websiteId = 1;

    private int $storeId = 1;

    private string $urlSuffix = '.html';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param list<int> $categoryIds
     * @param array<string, int> $images
     * @param array<string, array<string, int>> $options
     */
    public function prepare(array $categoryIds, array $images, array $options, string $urlSuffix): void
    {
        $this->categoryIds = $categoryIds;
        $this->images = $images;
        $this->options = $options;
        $this->urlSuffix = $urlSuffix;
        $store = $this->storeManager->getDefaultStoreView();
        $this->storeId = (int) $store->getId();
        $this->websiteId = (int) $store->getWebsiteId();
        $this->attributeSetId = (int) $this->eavConfig->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
        $codes = [
            'name', 'url_key', 'status', 'visibility', 'tax_class_id', 'price', 'special_price', 'weight', 'description',
            'short_description', 'meta_title', 'meta_description', 'image', 'small_image', 'thumbnail', 'swatch_image',
            'options_container', 'quantity_and_stock_status', 'media_gallery', 'brand', 'color', 'size', 'material',
            'pattern', 'season',
        ];
        foreach ($codes as $code) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
            if (!$attribute->getId()) {
                throw new \RuntimeException("Product attribute $code is missing");
            }
            $this->attributes[$code] = ['id' => (int) $attribute->getId(), 'table' => $attribute->getBackendTable()];
        }
    }

    /**
     * Writes the products in one transaction.
     *
     * @param list<array<string, mixed>> $products
     */
    public function write(array $products): void
    {
        $db = $this->resource->getConnection();
        $db->beginTransaction();
        try {
            $this->insert($db, $products);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    private function insert(AdapterInterface $db, array $products): void
    {
        $entities = [];
        foreach ($products as $p) {
            $configurable = $p['type'] === 'configurable';
            $entities[] = [
                'attribute_set_id' => $this->attributeSetId,
                'type_id' => $p['type'],
                'sku' => $p['sku'],
                'has_options' => $configurable ? 1 : 0,
                'required_options' => $configurable ? 1 : 0,
            ];
        }
        $this->insertRows($db, 'catalog_product_entity', $entities);
        $ids = $this->idsBySku($db, array_column($products, 'sku'));
        $related = [];
        foreach ($products as $p) {
            foreach ($p['related'] as $family) {
                $related[] = CatalogPlan::sku($family);
            }
        }
        $ids += $this->idsBySku($db, array_values(array_diff(array_unique($related), array_keys($ids))));

        $values = [];
        $websites = $categories = $stock = $sources = $gallery = $galleryValues = $rewrites = [];
        $superAttributes = $superLinks = $relations = $links = [];
        foreach ($products as $p) {
            $id = $ids[$p['sku']];
            $visible = $p['visibility'] !== 1;
            $inStock = $p['stock'] === null || $p['stock']['in_stock'];
            $this->value($values, 'name', $id, $p['name']);
            $this->value($values, 'url_key', $id, $p['url_key']);
            $this->value($values, 'status', $id, 1);
            $this->value($values, 'visibility', $id, $p['visibility']);
            $this->value($values, 'tax_class_id', $id, 2);
            $this->value($values, 'weight', $id, $p['weight']);
            $this->value($values, 'options_container', $id, 'container2');
            $this->value($values, 'quantity_and_stock_status', $id, $inStock ? 1 : 0);
            if ($p['price'] !== null) {
                $this->value($values, 'price', $id, $p['price']);
            }
            if ($p['special_price'] !== null) {
                $this->value($values, 'special_price', $id, $p['special_price']);
            }
            if ($visible) {
                $this->value($values, 'description', $id, $p['description']);
                $this->value($values, 'short_description', $id, $p['short_description']);
                $this->value($values, 'meta_title', $id, $p['name']);
                $this->value($values, 'meta_description', $id, strip_tags($p['short_description']));
            }
            foreach (['image', 'small_image', 'thumbnail', 'swatch_image'] as $role) {
                $this->value($values, $role, $id, $p['image']);
            }
            foreach ($p['options'] as $code => $label) {
                if ($label !== null) {
                    $this->value($values, $code, $id, $this->options[$code][$label]);
                }
            }

            $websites[] = ['product_id' => $id, 'website_id' => $this->websiteId];
            if ($visible) {
                $categories[] = ['category_id' => $this->categoryIds[$p['category']], 'product_id' => $id, 'position' => $p['family'] % 1000];
                $rewrites[] = [
                    'entity_type' => 'product',
                    'entity_id' => $id,
                    'request_path' => $p['url_key'] . $this->urlSuffix,
                    'target_path' => 'catalog/product/view/id/' . $id,
                    'redirect_type' => 0,
                    'store_id' => $this->storeId,
                    'is_autogenerated' => 1,
                ];
            }
            $qty = $p['stock']['qty'] ?? 0;
            $stock[] = [
                'product_id' => $id,
                'stock_id' => 1,
                'website_id' => 0,
                'qty' => $qty,
                'is_in_stock' => $inStock ? 1 : 0,
                'use_config_manage_stock' => 1,
                'manage_stock' => 1,
            ];
            if ($p['type'] === 'simple') {
                $sources[] = ['source_code' => 'default', 'sku' => $p['sku'], 'quantity' => $qty, 'status' => $inStock ? 1 : 0];
            }
            $valueId = $this->images[$p['image']];
            $gallery[] = ['value_id' => $valueId, 'entity_id' => $id];
            $galleryValues[] = [
                'value_id' => $valueId,
                'store_id' => Store::DEFAULT_STORE_ID,
                'entity_id' => $id,
                'label' => $p['name'],
                'position' => 1,
                'disabled' => 0,
            ];
            if ($p['type'] === 'configurable') {
                foreach (['color', 'size'] as $position => $code) {
                    $superAttributes[] = ['product_id' => $id, 'attribute_id' => $this->attributes[$code]['id'], 'position' => $position];
                }
            }
            if (isset($p['parent'])) {
                $superLinks[] = ['product_id' => $id, 'parent_id' => $ids[$p['parent']]];
                $relations[] = ['parent_id' => $ids[$p['parent']], 'child_id' => $id];
            }
            foreach ($p['related'] as $position => $family) {
                $target = $ids[CatalogPlan::sku($family)] ?? null;
                if ($target !== null) {
                    $links[] = ['product_id' => $id, 'linked_product_id' => $target, 'link_type_id' => 1, 'position' => $position + 1];
                }
            }
        }

        foreach ($values as $table => $rows) {
            $this->insertRows($db, $table, $rows);
        }
        $this->insertRows($db, 'catalog_product_website', $websites);
        $this->insertRows($db, 'catalog_category_product', $categories);
        $this->insertRows($db, 'cataloginventory_stock_item', $stock);
        $this->insertRows($db, 'inventory_source_item', $sources);
        $this->insertRows($db, 'catalog_product_entity_media_gallery_value_to_entity', $gallery);
        $this->insertRows($db, 'catalog_product_entity_media_gallery_value', $galleryValues);
        $this->insertRows($db, 'url_rewrite', $rewrites);
        $this->insertRows($db, 'catalog_product_super_link', $superLinks);
        $this->insertRows($db, 'catalog_product_relation', $relations);
        $this->writeSuperAttributes($db, $superAttributes);
        $this->writeLinks($db, $links);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $values
     */
    private function value(array &$values, string $code, int $entityId, mixed $value): void
    {
        $attribute = $this->attributes[$code];
        $values[$attribute['table']][] = [
            'attribute_id' => $attribute['id'],
            'store_id' => Store::DEFAULT_STORE_ID,
            'entity_id' => $entityId,
            'value' => $value,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function writeSuperAttributes(AdapterInterface $db, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->insertRows($db, 'catalog_product_super_attribute', $rows);
        $select = $db->select()
            ->from($this->resource->getTableName('catalog_product_super_attribute'), ['product_super_attribute_id'])
            ->where('product_id IN (?)', array_unique(array_column($rows, 'product_id')));
        $labels = [];
        foreach ($db->fetchCol($select) as $superAttributeId) {
            $labels[] = ['product_super_attribute_id' => $superAttributeId, 'store_id' => 0, 'use_default' => 1, 'value' => ''];
        }
        $this->insertRows($db, 'catalog_product_super_attribute_label', $labels);
    }

    /**
     * @param list<array{product_id: int, linked_product_id: int, link_type_id: int, position: int}> $rows
     */
    private function writeLinks(AdapterInterface $db, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $positions = [];
        $links = [];
        foreach ($rows as $row) {
            $positions[$row['product_id'] . '-' . $row['linked_product_id']] = $row['position'];
            unset($row['position']);
            $links[] = $row;
        }
        $this->insertRows($db, 'catalog_product_link', $links);
        $positionAttribute = (int) $db->fetchOne(
            $db->select()->from($this->resource->getTableName('catalog_product_link_attribute'), ['product_link_attribute_id'])
                ->where('link_type_id = 1')->where('product_link_attribute_code = ?', 'position')
        );
        $select = $db->select()
            ->from($this->resource->getTableName('catalog_product_link'), ['link_id', 'product_id', 'linked_product_id'])
            ->where('link_type_id = 1')
            ->where('product_id IN (?)', array_unique(array_column($rows, 'product_id')));
        $values = [];
        foreach ($db->fetchAll($select) as $link) {
            $values[] = [
                'product_link_attribute_id' => $positionAttribute,
                'link_id' => $link['link_id'],
                'value' => $positions[$link['product_id'] . '-' . $link['linked_product_id']] ?? 0,
            ];
        }
        $this->insertRows($db, 'catalog_product_link_attribute_int', $values);
    }

    /**
     * @param list<string> $skus
     * @return array<string, int>
     */
    private function idsBySku(AdapterInterface $db, array $skus): array
    {
        $ids = [];
        foreach (array_chunk($skus, 5000) as $chunk) {
            $select = $db->select()->from($this->resource->getTableName('catalog_product_entity'), ['sku', 'entity_id'])
                ->where('sku IN (?)', $chunk);
            $ids += array_map('intval', $db->fetchPairs($select));
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertRows(AdapterInterface $db, string $table, array $rows): void
    {
        $table = $this->resource->getTableName($table);
        foreach (array_chunk($rows, self::INSERT_ROWS) as $chunk) {
            $db->insertMultiple($table, $chunk);
        }
    }
}
