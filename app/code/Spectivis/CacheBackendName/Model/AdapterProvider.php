<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\CacheBackendName\Model;

use Magento\Framework\Cache\Frontend\Adapter\SymfonyAdapterProvider;
use Magento\Framework\Cache\Frontend\Adapter\SymfonyAdapters\TagAdapterInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Mage-OS 4 builds its caches with Symfony Cache and knows a backend only by
 * a short name: "redis", "valkey", "file" and so on. Any other name, such as
 * the class names Magento 2.4.7 and earlier used
 * (Magento\Framework\Cache\Backend\Redis, Cm_Cache_Backend_Redis), quietly
 * becomes the file cache. An env.php written for those versions then fills
 * var/cache and var/page_cache instead of using Redis or Valkey. This maps
 * those class names to "redis".
 */
class AdapterProvider extends SymfonyAdapterProvider
{
    private const REDIS_CLASSES = [
        'magento\framework\cache\backend\redis',
        'cm_cache_backend_redis',
    ];

    /**
     * @param array<string, mixed> $backendOptions
     */
    public function createAdapter(
        string $backendType,
        array $backendOptions,
        string $namespace = '',
        ?int $defaultLifetime = null
    ): CacheItemPoolInterface {
        return parent::createAdapter($this->shortName($backendType), $backendOptions, $namespace, $defaultLifetime);
    }

    /**
     * @param array<string, mixed> $backendOptions
     */
    public function createTagAdapter(
        string $backendType,
        CacheItemPoolInterface $cachePool,
        string $namespace = '',
        bool $isPageCache = false,
        array $backendOptions = []
    ): TagAdapterInterface {
        return parent::createTagAdapter($this->shortName($backendType), $cachePool, $namespace, $isPageCache, $backendOptions);
    }

    private function shortName(string $backendType): string
    {
        return in_array(strtolower(ltrim($backendType, '\\')), self::REDIS_CLASSES, true) ? 'redis' : $backendType;
    }
}
