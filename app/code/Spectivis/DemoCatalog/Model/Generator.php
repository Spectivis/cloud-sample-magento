<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;
use Magento\Framework\Indexer\ConfigInterface as IndexerConfig;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Lock\LockManagerInterface;
use Spectivis\DemoCatalog\Model\Setup\AttributeInstaller;
use Spectivis\DemoCatalog\Model\Setup\CategoryInstaller;
use Spectivis\DemoCatalog\Model\Setup\HomePageInstaller;
use Spectivis\DemoCatalog\Model\Setup\ImageInstaller;

/**
 * Generates the demo catalog in steps that can stop at any point and carry
 * on later: attributes, categories and images (each adds only what is
 * missing), then products a batch per transaction from the highest family
 * already written, then a full reindex. The last step is recorded in a flag,
 * so once the catalog is complete a run does nothing.
 */
class Generator
{
    public const RESULT_DONE = 'done';
    public const RESULT_MORE = 'more';
    public const RESULT_BUSY = 'busy';

    private const FLAG = 'spectivis_demo_catalog';
    private const LOCK = 'spectivis_demo_catalog';

    public function __construct(
        private readonly AttributeInstaller $attributeInstaller,
        private readonly CategoryInstaller $categoryInstaller,
        private readonly ImageInstaller $imageInstaller,
        private readonly HomePageInstaller $homePageInstaller,
        private readonly ProductWriter $productWriter,
        private readonly ResourceConnection $resource,
        private readonly FlagManager $flagManager,
        private readonly LockManagerInterface $lockManager,
        private readonly IndexerConfig $indexerConfig,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly CacheManager $cacheManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return array{skus: int, families: int, flag: array<string, mixed>}
     */
    public function status(): array
    {
        return [
            'skus' => $this->countSkus(),
            'families' => $this->nextFamily(),
            'flag' => (array) ($this->flagManager->getFlagData(self::FLAG) ?? []),
        ];
    }

    /**
     * @param int $seconds stop writing products after this long and return
     *     RESULT_MORE; 0 for no limit
     * @param callable(string): void $log
     */
    public function run(int $targetSkus, int $seed, int $batchSkus, int $seconds, string $indexerMode, callable $log): string
    {
        if (!$this->lockManager->lock(self::LOCK, 0)) {
            $log('another run is generating the catalog');

            return self::RESULT_BUSY;
        }
        try {
            return $this->generate($targetSkus, $seed, $batchSkus, $seconds, $indexerMode, $log);
        } finally {
            $this->lockManager->unlock(self::LOCK);
        }
    }

    /**
     * @param callable(string): void $log
     */
    private function generate(int $targetSkus, int $seed, int $batchSkus, int $seconds, string $indexerMode, callable $log): string
    {
        $flag = (array) ($this->flagManager->getFlagData(self::FLAG) ?? []);
        $next = $this->nextFamily();
        if (isset($flag['seed']) && (int) $flag['seed'] !== $seed && $next > 0) {
            throw new \RuntimeException(sprintf(
                'The catalog was generated with seed %d; it cannot continue with seed %d.',
                $flag['seed'],
                $seed
            ));
        }
        if (($flag['indexed_skus'] ?? 0) >= $targetSkus) {
            return self::RESULT_DONE;
        }
        $started = microtime(true);
        $flag['seed'] = $seed;
        $flag['started_at'] ??= gmdate('c');
        $this->flagManager->saveFlag(self::FLAG, $flag);

        $added = $this->attributeInstaller->install();
        $categories = $this->categoryInstaller->install();
        $images = $this->imageInstaller->install();
        if ($added > 0 || $categories['added'] > 0) {
            $log(sprintf('setup: %d attributes and options, %d categories added', $added, $categories['added']));
        }
        $this->productWriter->prepare(
            $categories['ids'],
            $images,
            $this->attributeInstaller->allOptionIds(),
            (string) $this->scopeConfig->getValue('catalog/seo/product_url_suffix')
        );

        $plan = new CatalogPlan($seed, $targetSkus);
        $total = $plan->familyCount();
        while ($next < $total) {
            if ($seconds > 0 && microtime(true) - $started >= $seconds) {
                return self::RESULT_MORE;
            }
            $products = [];
            $from = $next;
            while ($next < $total && count($products) < $batchSkus) {
                array_push($products, ...$plan->products($next++));
            }
            $batchStarted = microtime(true);
            $this->productWriter->write($products);
            $log(sprintf(
                'products: %d of %d SKUs (families %d to %d, %d SKUs in %.1f s)',
                $plan->skusBefore($next),
                $targetSkus,
                $from,
                $next - 1,
                count($products),
                microtime(true) - $batchStarted
            ));
        }

        $flag['written_at'] = gmdate('c');
        $flag['written_seconds'] = (int) ($flag['written_seconds'] ?? 0) + (int) (microtime(true) - $started);
        $this->flagManager->saveFlag(self::FLAG, $flag);
        $this->reindex($indexerMode, $log);
        if ($this->homePageInstaller->install()) {
            $log('home page: product grid added');
        }
        $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
        $flag['indexed_skus'] = $this->countSkus();
        $flag['indexed_at'] = gmdate('c');
        $this->flagManager->saveFlag(self::FLAG, $flag);
        $log(sprintf('done: %d SKUs, indexed, caches cleaned', $flag['indexed_skus']));

        return self::RESULT_DONE;
    }

    /**
     * @param callable(string): void $log
     */
    private function reindex(string $mode, callable $log): void
    {
        foreach (array_keys($this->indexerConfig->getIndexers()) as $indexerId) {
            $indexer = $this->indexerRegistry->get($indexerId);
            $started = microtime(true);
            $indexer->reindexAll();
            $log(sprintf('reindex: %s in %.1f s', $indexerId, microtime(true) - $started));
        }
        if ($mode === 'keep') {
            return;
        }
        foreach (array_keys($this->indexerConfig->getIndexers()) as $indexerId) {
            $indexer = $this->indexerRegistry->get($indexerId);
            try {
                $indexer->setScheduled($mode === 'schedule');
            } catch (\Throwable $e) {
                $log(sprintf('indexer %s stays in its mode: %s', $indexerId, $e->getMessage()));
            }
        }
        $log('indexers set to ' . ($mode === 'schedule' ? 'update by schedule' : 'update on save'));
    }

    private function countSkus(): int
    {
        $db = $this->resource->getConnection();

        return (int) $db->fetchOne(
            $db->select()->from($this->resource->getTableName('catalog_product_entity'), ['COUNT(*)'])
                ->where('sku LIKE ?', CatalogPlan::SKU_PREFIX . '%')
        );
    }

    /**
     * The first family not in the database yet. Batches are written whole,
     * so every family below the highest one is complete.
     */
    private function nextFamily(): int
    {
        $db = $this->resource->getConnection();
        $sku = $db->fetchOne(
            $db->select()->from($this->resource->getTableName('catalog_product_entity'), ['MAX(sku)'])
                ->where('sku LIKE ?', CatalogPlan::SKU_PREFIX . '%')
        );
        $family = is_string($sku) ? CatalogPlan::familyOf($sku) : null;

        return $family === null ? 0 : $family + 1;
    }
}
