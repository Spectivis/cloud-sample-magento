<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

/**
 * Generator settings, read from environment variables so a host with no
 * console can set them:
 *
 * - DEMO_CATALOG_GENERATE: 1 lets the cron job generate the catalog.
 * - DEMO_CATALOG_SKUS: how many SKUs, configurable children included (50000).
 * - DEMO_CATALOG_SEED: the same seed gives the same catalog (1).
 * - DEMO_CATALOG_BATCH: SKUs written per database transaction (2000).
 * - DEMO_CATALOG_RUN_SECONDS: how long one cron run keeps writing before it
 *   stops and leaves the rest to the next run (240).
 * - DEMO_CATALOG_INDEXER_MODE: `schedule` (default) or `realtime`, the indexer
 *   mode set once the catalog is indexed. `keep` leaves it alone.
 */
class Config
{
    public const DEFAULT_SKUS = 50000;

    public function isCronEnabled(): bool
    {
        return in_array(strtolower((string) $this->env('DEMO_CATALOG_GENERATE', '0')), ['1', 'true', 'yes', 'on'], true);
    }

    public function targetSkus(): int
    {
        return max(1, (int) $this->env('DEMO_CATALOG_SKUS', (string) self::DEFAULT_SKUS));
    }

    public function seed(): int
    {
        return (int) $this->env('DEMO_CATALOG_SEED', '1');
    }

    public function batchSkus(): int
    {
        return min(20000, max(100, (int) $this->env('DEMO_CATALOG_BATCH', '2000')));
    }

    public function runSeconds(): int
    {
        return max(10, (int) $this->env('DEMO_CATALOG_RUN_SECONDS', '240'));
    }

    public function indexerMode(): string
    {
        $mode = strtolower((string) $this->env('DEMO_CATALOG_INDEXER_MODE', 'schedule'));

        return in_array($mode, ['schedule', 'realtime', 'keep'], true) ? $mode : 'schedule';
    }

    private function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }
}
