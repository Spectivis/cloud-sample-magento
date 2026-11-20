<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Spectivis\DemoCatalog\Model\Vocabulary;

/**
 * Makes the filterable attributes and their options, and adds them to the
 * default attribute set. Adds only what is missing, so it can run every time.
 */
class AttributeInstaller
{
    private const GROUP = 'Product Details';

    public function __construct(
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavConfig $eavConfig,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array<string, list<string>> attribute code => option labels
     */
    public static function attributes(): array
    {
        $sizes = array_merge(...array_values(Vocabulary::SIZES));

        return [
            'brand' => Vocabulary::BRANDS,
            'color' => Vocabulary::COLORS,
            'size' => $sizes,
            'material' => array_values(array_unique(array_merge(
                ...array_column(Vocabulary::DEPARTMENTS, 'materials'),
                ...array_values(Vocabulary::SUB_MATERIALS)
            ))),
            'pattern' => Vocabulary::PATTERNS,
            'season' => Vocabulary::SEASONS,
        ];
    }

    /**
     * @return int how many attributes or options were added
     */
    public function install(): int
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $added = 0;
        $order = 10;
        foreach (self::attributes() as $code => $labels) {
            $attributeId = $eavSetup->getAttributeId(Product::ENTITY, $code);
            if (!$attributeId) {
                $eavSetup->addAttribute(Product::ENTITY, $code, [
                    'type' => 'int',
                    'label' => $code === 'color' ? 'Color' : ucfirst($code),
                    'input' => 'select',
                    'source' => Table::class,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'required' => false,
                    'user_defined' => true,
                    'group' => self::GROUP,
                    'sort_order' => 100 + $order,
                    'apply_to' => 'simple,virtual,configurable',
                ]);
                $attributeId = $eavSetup->getAttributeId(Product::ENTITY, $code);
                $added++;
            }
            $this->makeFilterable($eavSetup, $code, $order);
            $eavSetup->addAttributeToGroup(Product::ENTITY, 'Default', self::GROUP, $code, 100 + $order);
            $missing = array_values(array_diff($labels, array_keys($this->optionIds((int) $attributeId))));
            if ($missing !== []) {
                $eavSetup->addAttributeOption(['attribute_id' => $attributeId, 'values' => $missing]);
                $added += count($missing);
            }
            $order += 10;
        }
        $this->eavConfig->clear();

        return $added;
    }

    /**
     * @return array<string, array<string, int>> attribute code => option label => option id
     */
    public function allOptionIds(): array
    {
        $options = [];
        foreach (array_keys(self::attributes()) as $code) {
            $options[$code] = $this->optionIds((int) $this->eavConfig->getAttribute(Product::ENTITY, $code)->getId());
        }

        return $options;
    }

    /**
     * @return array<string, int> option label => option id, admin labels
     */
    public function optionIds(int $attributeId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['o' => $this->resource->getTableName('eav_attribute_option')], [])
            ->join(
                ['v' => $this->resource->getTableName('eav_attribute_option_value')],
                'v.option_id = o.option_id AND v.store_id = 0',
                ['value', 'option_id']
            )
            ->where('o.attribute_id = ?', $attributeId);

        return array_map('intval', $connection->fetchPairs($select));
    }

    private function makeFilterable(EavSetup $eavSetup, string $code, int $order): void
    {
        $searchable = in_array($code, ['brand', 'color', 'material'], true) ? 1 : 0;
        foreach ([
            'is_global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'is_filterable' => 1,
            'is_filterable_in_search' => 1,
            'is_searchable' => $searchable,
            'is_visible_on_front' => 1,
            'is_comparable' => 1,
            'used_in_product_listing' => $code === 'brand' ? 1 : 0,
            'is_used_in_grid' => 1,
            'is_filterable_in_grid' => 1,
            'position' => $order,
        ] as $field => $value) {
            $eavSetup->updateAttribute(Product::ENTITY, $code, $field, $value);
        }
    }
}
