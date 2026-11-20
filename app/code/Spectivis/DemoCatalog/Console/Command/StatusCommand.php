<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Console\Command;

use Spectivis\DemoCatalog\Model\Config;
use Spectivis\DemoCatalog\Model\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento demo:catalog:status`: how far the demo catalog has got.
 */
class StatusCommand extends Command
{
    public function __construct(
        private readonly Generator $generator,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('demo:catalog:status')->setDescription('Show how far the demo catalog has got');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->generator->status();
        $output->writeln(sprintf('SKUs written:   %d of %d', $status['skus'], $this->config->targetSkus()));
        $output->writeln(sprintf('Cron generator: %s', $this->config->isCronEnabled() ? 'on (DEMO_CATALOG_GENERATE)' : 'off'));
        foreach ($status['flag'] as $key => $value) {
            $output->writeln(sprintf('%-15s %s', str_replace('_', ' ', (string) $key) . ':', (string) $value));
        }

        return Command::SUCCESS;
    }
}
