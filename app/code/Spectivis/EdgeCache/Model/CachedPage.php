<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\EdgeCache\Model;

/**
 * Remembers, for the current request, that the page is one Magento's built-in
 * full page cache served or stored. Those pages are the same for every visitor
 * without the X-Magento-Vary cookie, so a CDN can keep them too.
 */
class CachedPage
{
    private bool $cached = false;

    private bool $varies = false;

    public function mark(): void
    {
        $this->cached = true;
    }

    /**
     * The visitor sent the X-Magento-Vary cookie (logged in, cart, other
     * currency): their pages differ from the shared ones, so a CDN must not keep them.
     */
    public function setVaries(bool $varies): void
    {
        $this->varies = $varies;
    }

    public function isShareable(): bool
    {
        return $this->cached && !$this->varies;
    }

    /**
     * Seconds a CDN may keep the page, from EDGE_CACHE_TTL (default 600, 0 turns it off).
     */
    public function ttl(): int
    {
        $ttl = getenv('EDGE_CACHE_TTL');
        return $ttl === false || $ttl === '' ? 600 : max(0, (int) $ttl);
    }
}
