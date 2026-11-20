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
 * Draws the placeholder product images with GD into
 * pub/media/catalog/product, and gives each one a media gallery row that
 * every product using it links to. Three images per department: 24 files of
 * about 15 KB for the whole catalog.
 *
 * Products share the gallery rows. Removing an image from one product in the
 * admin removes it from every product that shares it.
 */
class ImageInstaller
{
    private const SIZE = 800;

    /** Background colours, one per department. */
    private const PALETTE = [
        [0xE8, 0xD5, 0xC4], [0xC9, 0xD6, 0xE3], [0xF3, 0xE1, 0x9B], [0xD8, 0xC3, 0xA5],
        [0xCF, 0xC6, 0xD9], [0xD5, 0xE3, 0xD1], [0xB8, 0xD4, 0xC2], [0xF0, 0xC9, 0xB8],
    ];

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
        $departments = array_keys(Vocabulary::DEPARTMENTS);
        foreach (CatalogPlan::imageFiles() as $i => $file) {
            $path = 'catalog/product' . $file;
            if ($media->isFile($path)) {
                continue;
            }
            $department = intdiv($i, CatalogPlan::IMAGES_PER_DEPARTMENT);
            $media->writeFile($path, $this->draw($departments[$department], self::PALETTE[$department], $i % CatalogPlan::IMAGES_PER_DEPARTMENT));
        }

        return $this->galleryRows();
    }

    /**
     * @return array<string, int>
     */
    private function galleryRows(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_entity_media_gallery');
        $attributeId = (int) $this->eavConfig->getAttribute(Product::ENTITY, 'media_gallery')->getId();
        $files = CatalogPlan::imageFiles();
        $select = $connection->select()->from($table, ['value', 'value_id'])
            ->where('attribute_id = ?', $attributeId)->where('value IN (?)', $files);
        $rows = array_map('intval', $connection->fetchPairs($select));
        foreach ($files as $file) {
            if (!isset($rows[$file])) {
                $connection->insert($table, ['attribute_id' => $attributeId, 'value' => $file, 'media_type' => 'image', 'disabled' => 0]);
                $rows[$file] = (int) $connection->lastInsertId($table);
            }
        }

        return $rows;
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function draw(string $label, array $rgb, int $variant): string
    {
        $size = self::SIZE;
        $image = imagecreatetruecolor($size, $size);
        $background = imagecolorallocate($image, ...$rgb);
        $shade = imagecolorallocate($image, (int) ($rgb[0] * 0.8), (int) ($rgb[1] * 0.8), (int) ($rgb[2] * 0.8));
        $ink = imagecolorallocate($image, 0x33, 0x33, 0x33);
        imagefill($image, 0, 0, $background);
        match ($variant) {
            0 => imagefilledellipse($image, $size / 2, $size / 2 - 40, 420, 420, $shade),
            1 => imagefilledrectangle($image, 190, 150, 610, 570, $shade),
            default => imagefilledpolygon($image, [400, 130, 640, 570, 160, 570], $shade),
        };

        // GD's built-in font is small: draw the text on a strip and scale it up.
        $text = strtoupper($label);
        $strip = imagecreatetruecolor(imagefontwidth(5) * strlen($text) + 8, imagefontheight(5) + 4);
        imagefill($strip, 0, 0, $background);
        imagestring($strip, 5, 4, 2, $text, $ink);
        $width = min($size - 80, imagesx($strip) * 3);
        $height = (int) round(imagesy($strip) * $width / imagesx($strip));
        imagecopyresampled($image, $strip, (int) (($size - $width) / 2), 640, 0, 0, $width, $height, imagesx($strip), imagesy($strip));
        imagestring($image, 3, 20, $size - 24, 'Demo image', $ink);

        ob_start();
        imagejpeg($image, null, 80);

        return (string) ob_get_clean();
    }
}
