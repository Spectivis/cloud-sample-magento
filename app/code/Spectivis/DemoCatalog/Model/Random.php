<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

/**
 * A deterministic random stream for one key (the seed and a family number),
 * so any product can be generated again on its own, in any order, with the
 * same result.
 */
class Random
{
    private int $counter = 0;

    public function __construct(private readonly string $key)
    {
    }

    public static function for(int $seed, string $scope, int $index): self
    {
        return new self($seed . '|' . $scope . '|' . $index);
    }

    /**
     * A float in [0, 1).
     */
    public function float(): float
    {
        $hash = hash('xxh64', $this->key . '|' . $this->counter++);

        return hexdec(substr($hash, 0, 13)) / 4503599627370496;
    }

    public function int(int $min, int $max): int
    {
        return $min + (int) floor($this->float() * ($max - $min + 1));
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /**
     * @template T
     * @param list<T> $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    /**
     * @template T
     * @param list<T> $items
     * @return list<T>
     */
    public function sample(array $items, int $count): array
    {
        $keys = array_keys($items);
        $picked = [];
        while ($keys !== [] && count($picked) < $count) {
            $at = $this->int(0, count($keys) - 1);
            $picked[] = $items[$keys[$at]];
            array_splice($keys, $at, 1);
        }

        return $picked;
    }
}
