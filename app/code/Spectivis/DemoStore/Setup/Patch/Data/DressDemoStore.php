<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoStore\Setup\Patch\Data;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Magento\Theme\Model\Theme\Registration;

/**
 * Makes the store look like a shop: the Spectivis/demo theme (Hyvä), a store
 * name and a footer line, and no Hyvä demo content. Settings changed in the
 * admin afterwards stay; the patch runs once.
 */
class DressDemoStore implements DataPatchInterface
{
    private const THEME = 'Spectivis/demo';

    private const SETTINGS = [
        'general/store_information/name' => 'Larkspur',
        'design/head/default_title' => 'Larkspur',
        'design/head/title_prefix' => '',
        'design/head/title_suffix' => ' | Larkspur',
        'design/header/logo_alt' => 'Larkspur',
        'design/footer/copyright' => 'Larkspur is a demo store on Spectivis Cloud. Products, brands and prices are made up. Photos from Pixabay.',
        'hyva_theme_general/demo_content/show_homepage_demo_content' => '0',
    ];

    public function __construct(
        private readonly Registration $themeRegistration,
        private readonly ThemeCollectionFactory $themeCollectionFactory,
        private readonly WriterInterface $configWriter,
        private readonly ResourceConnection $resource,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    public function apply(): self
    {
        // Themes are registered by Magento_Theme's recurring step, which can run after this patch.
        $this->themeRegistration->register();
        $theme = $this->themeCollectionFactory->create()
            ->addAreaFilter(Area::AREA_FRONTEND)
            ->addFieldToFilter('theme_path', self::THEME)
            ->getFirstItem();

        if ($theme->getId()) {
            // A theme set for a website or store view would win over the default.
            $this->resource->getConnection()->delete(
                $this->resource->getTableName('core_config_data'),
                ['path = ?' => 'design/theme/theme_id', 'scope <> ?' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT]
            );
            $this->configWriter->save('design/theme/theme_id', (string) $theme->getId());
            $this->indexerRegistry->get('design_config_grid')->reindexAll();
        }

        foreach (self::SETTINGS as $path => $value) {
            $this->configWriter->save($path, $value);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
