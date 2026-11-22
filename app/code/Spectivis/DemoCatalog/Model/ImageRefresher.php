<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\Store;
use Spectivis\DemoCatalog\Model\Setup\ImageInstaller;

/**
 * Moves products written by an earlier version, with one placeholder image
 * per department, to the photos their subcategory has now, then deletes the
 * placeholders. Products are found by their SKU, so it works on any catalog
 * this module wrote, in batches of one transaction each.
 */
class ImageRefresher
{
    private const BATCH = 2000;

    private const ROLES = ['image', 'small_image', 'thumbnail', 'swatch_image'];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavConfig $eavConfig,
        private readonly ImageInstaller $imageInstaller
    ) {
    }

    /**
     * @param array<string, int> $images image file => media gallery value id
     * @param callable(string): void $log
     */
    public function refresh(CatalogPlan $plan, array $images, callable $log): int
    {
        $db = $this->resource->getConnection();
        $entities = $this->resource->getTableName('catalog_product_entity');
        $varchar = $this->resource->getTableName('catalog_product_entity_varchar');
        $toEntity = $this->resource->getTableName('catalog_product_entity_media_gallery_value_to_entity');
        $galleryValues = $this->resource->getTableName('catalog_product_entity_media_gallery_value');
        $nameId = (int) $this->eavConfig->getAttribute(Product::ENTITY, 'name')->getId();
        $roleIds = array_map(
            fn (string $code): int => (int) $this->eavConfig->getAttribute(Product::ENTITY, $code)->getId(),
            self::ROLES
        );

        $done = 0;
        $after = 0;
        do {
            $skus = $db->fetchPairs($db->select()->from($entities, ['entity_id', 'sku'])
                ->where('sku LIKE ?', CatalogPlan::SKU_PREFIX . '%')
                ->where('entity_id > ?', $after)
                ->order('entity_id')
                ->limit(self::BATCH));
            if ($skus === []) {
                break;
            }
            $after = (int) array_key_last($skus);
            $names = $db->fetchPairs($db->select()->from($varchar, ['entity_id', 'value'])
                ->where('attribute_id = ?', $nameId)
                ->where('store_id = ?', Store::DEFAULT_STORE_ID)
                ->where('entity_id IN (?)', array_keys($skus)));

            $values = $links = $labels = [];
            foreach ($skus as $id => $sku) {
                $family = CatalogPlan::familyOf((string) $sku);
                if ($family === null || $family >= $plan->familyCount()) {
                    continue;
                }
                $file = $plan->imageOf($family);
                foreach ($roleIds as $attributeId) {
                    $values[] = ['attribute_id' => $attributeId, 'store_id' => Store::DEFAULT_STORE_ID, 'entity_id' => (int) $id, 'value' => $file];
                }
                $links[] = ['value_id' => $images[$file], 'entity_id' => (int) $id];
                $labels[] = [
                    'value_id' => $images[$file],
                    'store_id' => Store::DEFAULT_STORE_ID,
                    'entity_id' => (int) $id,
                    'label' => $names[$id] ?? null,
                    'position' => 1,
                    'disabled' => 0,
                ];
            }
            if ($values === []) {
                continue;
            }
            $db->beginTransaction();
            try {
                $db->insertOnDuplicate($varchar, $values, ['value']);
                $db->delete($galleryValues, [
                    'entity_id IN (?)' => array_column($links, 'entity_id'),
                    'value_id IN (?)' => array_values($images),
                ]);
                $db->insertMultiple($galleryValues, $labels);
                $db->insertOnDuplicate($toEntity, $links, ['value_id']);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            $done += count($links);
            $log(sprintf('images: %d products moved to the new photos', $done));
        } while (count($skus) === self::BATCH);

        $this->imageInstaller->removeLegacy();

        return $done;
    }
}
