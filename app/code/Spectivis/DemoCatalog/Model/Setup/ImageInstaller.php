<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Spectivis\DemoCatalog\Model\CatalogPlan;
use Spectivis\DemoCatalog\Model\Vocabulary;

/**
 * Copies the product photos in this module's images folder (three per
 * subcategory, from Pixabay) into pub/media/catalog/product, and gives each
 * one a media gallery row that every product using it links to.
 *
 * Products share the gallery rows. Removing an image from one product in the
 * admin removes it from every product that shares it.
 */
class ImageInstaller
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ResourceConnection $resource,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @return array<string, int> file => media gallery value id
     */
    public function install(): array
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        foreach (CatalogPlan::imageFiles() as $file) {
            $path = 'catalog/product' . $file;
            if ($media->isFile($path)) {
                continue;
            }
            $source = dirname(__DIR__, 2) . '/images/' . basename($file);
            $photo = is_file($source) ? file_get_contents($source) : false;
            if ($photo === false) {
                throw new \RuntimeException('Missing product photo ' . $source);
            }
            $media->writeFile($path, $photo);
        }

        return $this->galleryRows(CatalogPlan::imageFiles(), true);
    }

    /**
     * The placeholder images earlier versions drew, three per department,
     * with their gallery rows where they still exist.
     *
     * @return array<string, int>
     */
    public function legacyImages(): array
    {
        $files = [];
        foreach (array_keys(Vocabulary::DEPARTMENTS) as $department) {
            for ($n = 1; $n <= 3; $n++) {
                $files[] = sprintf('/s/p/spectivis-demo-%s-%d.jpg', Vocabulary::slug($department), $n);
            }
        }

        return $this->galleryRows($files, false);
    }

    /**
     * Deletes the legacy placeholder files and gallery rows. The database
     * removes the products' links to the rows with them.
     */
    public function removeLegacy(): void
    {
        $legacy = $this->legacyImages();
        if ($legacy !== []) {
            $this->resource->getConnection()->delete(
                $this->resource->getTableName('catalog_product_entity_media_gallery'),
                ['value_id IN (?)' => array_values($legacy)]
            );
        }
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        foreach (array_keys($legacy) as $file) {
            if ($media->isFile('catalog/product' . $file)) {
                $media->delete('catalog/product' . $file);
            }
        }
    }

    /**
     * @param list<string> $files
     * @return array<string, int>
     */
    private function galleryRows(array $files, bool $create): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_entity_media_gallery');
        $attributeId = (int) $this->eavConfig->getAttribute(Product::ENTITY, 'media_gallery')->getId();
        $select = $connection->select()->from($table, ['value', 'value_id'])
            ->where('attribute_id = ?', $attributeId)->where('value IN (?)', $files);
        $rows = array_map('intval', $connection->fetchPairs($select));
        if ($create) {
            foreach ($files as $file) {
                if (!isset($rows[$file])) {
                    $connection->insert($table, ['attribute_id' => $attributeId, 'value' => $file, 'media_type' => 'image', 'disabled' => 0]);
                    $rows[$file] = (int) $connection->lastInsertId($table);
                }
            }
        }

        return $rows;
    }
}
