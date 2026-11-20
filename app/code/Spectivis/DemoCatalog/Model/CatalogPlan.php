<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

/**
 * What the catalog holds, worked out from the seed and the SKU count alone.
 *
 * The catalog is a list of families. A family is one simple product, or one
 * configurable product with a child for each colour and size. Family n has
 * the SKU `DC-<n, six digits>`; its children add the colour and the size.
 * Every family is drawn from its own random stream, so it comes out the same
 * however the work is split, and a run that stopped half way can carry on
 * from the highest family in the database.
 */
class CatalogPlan
{
    public const SKU_PREFIX = 'DC-';
    public const IMAGES_PER_DEPARTMENT = 3;

    private const RELATED_PER_PRODUCT = 4;

    /** @var list<array{leaf: int, configurable: bool, colors: list<string>, sizes: list<string>, skus: int}> */
    private array $families = [];

    /** @var list<int> SKUs before each family */
    private array $offsets = [];

    /** @var list<array<string, mixed>> */
    private array $leaves;

    public function __construct(private readonly int $seed, private readonly int $targetSkus)
    {
        $this->leaves = Vocabulary::leaves();
        $total = 0;
        for ($family = 0; $total < $targetSkus; $family++) {
            $shape = $this->shape($family, $targetSkus - $total);
            $this->offsets[] = $total;
            $this->families[] = $shape;
            $total += $shape['skus'];
        }
    }

    public static function sku(int $family): string
    {
        return sprintf('%s%06d', self::SKU_PREFIX, $family);
    }

    /**
     * The family number of a SKU this plan made, or null.
     */
    public static function familyOf(string $sku): ?int
    {
        return preg_match('/^DC-(\d{6})/', $sku, $m) ? (int) $m[1] : null;
    }

    public function familyCount(): int
    {
        return count($this->families);
    }

    public function skusBefore(int $family): int
    {
        return $family >= count($this->families) ? $this->targetSkus : $this->offsets[$family];
    }

    public function skusIn(int $family): int
    {
        return $this->families[$family]['skus'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leaves(): array
    {
        return $this->leaves;
    }

    /**
     * The image files, relative to pub/media/catalog/product, one set per department.
     *
     * @return list<string>
     */
    public static function imageFiles(): array
    {
        $files = [];
        foreach (array_keys(Vocabulary::DEPARTMENTS) as $department) {
            for ($n = 1; $n <= self::IMAGES_PER_DEPARTMENT; $n++) {
                $files[] = sprintf('/s/p/spectivis-demo-%s-%d.jpg', Vocabulary::slug($department), $n);
            }
        }

        return $files;
    }

    /**
     * The products of one family: the simple or configurable product first,
     * then the children.
     *
     * @return list<array<string, mixed>>
     */
    public function products(int $family): array
    {
        $shape = $this->families[$family];
        $leaf = $this->leaves[$shape['leaf']];
        $department = Vocabulary::DEPARTMENTS[$leaf['department']];
        $random = Random::for($this->seed, 'product', $family);

        $brand = $random->pick(Vocabulary::BRANDS);
        $adjective = $random->pick(Vocabulary::ADJECTIVES);
        $material = $random->pick(Vocabulary::SUB_MATERIALS[$leaf['sub']] ?? $department['materials']);
        $name = implode(' ', array_filter([$brand, $adjective, $random->chance(0.7) ? $material : null, $leaf['noun']]));
        [$low, $high] = $department['price'];
        $price = $this->price($low * ($high / $low) ** ($random->float() ** 1.6));
        $specialPrice = $random->chance(0.15) ? $this->price($price * (0.6 + 0.3 * $random->float())) : null;
        if ($specialPrice !== null && $specialPrice >= $price) {
            $specialPrice = null;
        }
        $features = $random->sample(Vocabulary::FEATURES, 3);
        $sized = $leaf['size'] !== null;
        $options = [
            'brand' => $brand,
            'material' => $material,
            'color' => $random->pick(Vocabulary::COLORS),
            'pattern' => in_array($leaf['department'], ['Women', 'Men', 'Kids', 'Bags & Accessories', 'Home & Living'], true)
                ? $random->pick(Vocabulary::PATTERNS) : null,
            'season' => $sized ? $random->pick(Vocabulary::SEASONS) : null,
            'size' => $sized ? $random->pick(Vocabulary::SIZES[$leaf['size']]) : null,
        ];
        $sku = self::sku($family);
        $image = self::imageFiles()[$leaf['departmentIndex'] * self::IMAGES_PER_DEPARTMENT
            + $random->int(0, self::IMAGES_PER_DEPARTMENT - 1)];
        $description = $this->description($random, $name, $brand, $adjective, $material, $leaf, $features);
        $base = [
            'family' => $family,
            'category' => $shape['leaf'],
            'image' => $image,
            'brand' => $brand,
            'weight' => round(0.1 + 4.9 * $random->float() ** 2, 2),
            'description' => $description,
            'short_description' => sprintf(
                '<p>%s %s in %s, with %s.</p>',
                $adjective,
                strtolower($leaf['noun']),
                strtolower($material),
                $features[0]
            ),
        ];
        $main = $base + [
            'sku' => $sku,
            'type' => $shape['configurable'] ? 'configurable' : 'simple',
            'name' => $name,
            'url_key' => Vocabulary::slug($name . ' ' . $sku),
            'visibility' => 4,
            'price' => $shape['configurable'] ? null : $price,
            'special_price' => $shape['configurable'] ? null : $specialPrice,
            'options' => $shape['configurable'] ? array_diff_key($options, ['color' => 0, 'size' => 0]) : $options,
            'stock' => $shape['configurable'] ? null : $this->stock($random),
            'related' => $this->related($family, $random),
        ];
        $products = [$main];
        foreach ($shape['colors'] as $color) {
            foreach ($shape['sizes'] as $size) {
                $childSku = sprintf('%s-%s-%s', $sku, strtoupper(Vocabulary::slug($color)), strtoupper(str_replace('-', '', Vocabulary::slug($size))));
                $childName = sprintf('%s %s %s', $name, $color, $size);
                $products[] = $base + [
                    'sku' => $childSku,
                    'type' => 'simple',
                    'name' => $childName,
                    'url_key' => Vocabulary::slug($childName . ' ' . $childSku),
                    'visibility' => 1,
                    'price' => $price,
                    'special_price' => $specialPrice,
                    'options' => ['color' => $color, 'size' => $size] + $options,
                    'stock' => $this->stock($random),
                    'related' => [],
                    'parent' => $sku,
                ];
            }
        }

        return $products;
    }

    /**
     * @return array{leaf: int, configurable: bool, colors: list<string>, sizes: list<string>, skus: int}
     */
    private function shape(int $family, int $remaining): array
    {
        $random = Random::for($this->seed, 'family', $family);
        $leafIndex = $random->int(0, count($this->leaves) - 1);
        $leaf = $this->leaves[$leafIndex];
        $shape = ['leaf' => $leafIndex, 'configurable' => false, 'colors' => [], 'sizes' => [], 'skus' => 1];
        $probability = Vocabulary::DEPARTMENTS[$leaf['department']]['configurable'];
        if ($leaf['size'] === null || !$random->chance($probability)) {
            return $shape;
        }
        $colors = $random->sample(Vocabulary::COLORS, $random->int(2, 3));
        $range = Vocabulary::SIZES[$leaf['size']];
        $count = min(count($range), $random->int(3, 5));
        $sizes = array_slice($range, $random->int(0, count($range) - $count), $count);
        $skus = 1 + count($colors) * count($sizes);
        if ($skus > $remaining) {
            return $shape;
        }

        return ['leaf' => $leafIndex, 'configurable' => true, 'colors' => $colors, 'sizes' => $sizes, 'skus' => $skus];
    }

    /**
     * Up to four earlier families from the same leaf category, so the linked
     * products exist when this one is written.
     *
     * @return list<int>
     */
    private function related(int $family, Random $random): array
    {
        $leaf = $this->families[$family]['leaf'];
        $candidates = [];
        for ($other = $family - 1; $other >= 0 && count($candidates) < 30; $other--) {
            if ($this->families[$other]['leaf'] === $leaf) {
                $candidates[] = $other;
            }
        }

        return $random->sample($candidates, self::RELATED_PER_PRODUCT);
    }

    /**
     * @return array{qty: int, in_stock: bool}
     */
    private function stock(Random $random): array
    {
        $qty = $random->chance(0.06) ? 0 : $random->int(1, 250);

        return ['qty' => $qty, 'in_stock' => $qty > 0];
    }

    private function price(float $amount): float
    {
        return $amount < 10 ? floor($amount) + 0.49 : floor($amount) + 0.99;
    }

    /**
     * @param array<string, mixed> $leaf
     * @param list<string> $features
     */
    private function description(
        Random $random,
        string $name,
        string $brand,
        string $adjective,
        string $material,
        array $leaf,
        array $features
    ): string {
        $noun = strtolower($leaf['noun']);
        $closers = [
            sprintf('Part of the %s %s range, it pairs well with the rest of the %s department.', $brand, strtolower($leaf['sub']), $leaf['department']),
            'A customer favourite that has been reordered season after season.',
            sprintf('Designed in house by %s and tested for everyday use.', $brand),
            'Free returns within 30 days. Care instructions are on the label.',
            sprintf('Customers looking for %s often choose this one for its %s.', strtolower($leaf['leaf']), $features[2]),
        ];

        $closing = $random->sample($closers, 2);

        return sprintf(
            '<p>The %s is %s %s %s from %s, made from %s with %s and %s.</p><p>%s %s</p>',
            htmlspecialchars($name),
            preg_match('/^[aeiou]/i', $adjective) ? 'an' : 'a',
            strtolower($adjective),
            htmlspecialchars($noun),
            htmlspecialchars($brand),
            strtolower($material),
            $features[0],
            $features[1],
            htmlspecialchars($closing[0]),
            htmlspecialchars($closing[1])
        );
    }
}
