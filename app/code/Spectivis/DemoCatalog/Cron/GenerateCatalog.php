<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Cron;

use Psr\Log\LoggerInterface;
use Spectivis\DemoCatalog\Model\Config;
use Spectivis\DemoCatalog\Model\Generator;

/**
 * Generates the demo catalog from cron when DEMO_CATALOG_GENERATE is on: each
 * run writes products for up to DEMO_CATALOG_RUN_SECONDS, the run that
 * writes the last batch reindexes, and later runs find the catalog complete
 * and stop at once.
 */
class GenerateCatalog
{
    public function __construct(
        private readonly Generator $generator,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isCronEnabled()) {
            return;
        }
        $this->generator->run(
            $this->config->targetSkus(),
            $this->config->seed(),
            $this->config->batchSkus(),
            $this->config->runSeconds(),
            $this->config->indexerMode(),
            fn (string $line) => $this->logger->info('demo catalog: ' . $line)
        );
    }
}
