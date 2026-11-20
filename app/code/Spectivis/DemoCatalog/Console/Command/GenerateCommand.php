<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Spectivis\DemoCatalog\Model\Config;
use Spectivis\DemoCatalog\Model\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento demo:catalog:generate`: generates the demo catalog in one go,
 * or for a limited time with --seconds. Defaults come from the DEMO_CATALOG_*
 * variables.
 */
class GenerateCommand extends Command
{
    public function __construct(
        private readonly Generator $generator,
        private readonly Config $config,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('demo:catalog:generate')
            ->setDescription('Generate the demo catalog, or carry on generating it')
            ->addOption('skus', null, InputOption::VALUE_REQUIRED, 'SKUs, configurable children included (DEMO_CATALOG_SKUS)')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Seed (DEMO_CATALOG_SEED)')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'SKUs per transaction (DEMO_CATALOG_BATCH)')
            ->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Stop writing products after this many seconds; 0 runs to the end', '0')
            ->addOption('indexer-mode', null, InputOption::VALUE_REQUIRED, 'schedule, realtime or keep (DEMO_CATALOG_INDEXER_MODE)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        $result = $this->generator->run(
            (int) ($input->getOption('skus') ?? $this->config->targetSkus()),
            (int) ($input->getOption('seed') ?? $this->config->seed()),
            (int) ($input->getOption('batch') ?? $this->config->batchSkus()),
            (int) $input->getOption('seconds'),
            (string) ($input->getOption('indexer-mode') ?? $this->config->indexerMode()),
            fn (string $line) => $output->writeln($line)
        );
        $output->writeln(match ($result) {
            Generator::RESULT_DONE => 'The catalog is complete.',
            Generator::RESULT_MORE => 'Stopped for now; run the command again to carry on.',
            default => 'Another run is generating the catalog.',
        });

        return $result === Generator::RESULT_BUSY ? Command::FAILURE : Command::SUCCESS;
    }
}
