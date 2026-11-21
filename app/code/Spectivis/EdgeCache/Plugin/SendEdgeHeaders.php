<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\EdgeCache\Plugin;

use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\Response\Http as Response;
use Spectivis\EdgeCache\Model\CachedPage;

/**
 * The built-in full page cache always tells browsers and CDNs "no-cache", and a
 * session cookie goes out with every page, so a CDN has to ask the origin for
 * every page view. For pages the full page cache holds, and visitors without
 * the X-Magento-Vary cookie (not logged in, empty cart), send a shared-cache
 * lifetime instead and no cookies. Browsers still revalidate (max-age=0).
 *
 * Runs just before the headers go out, after Magento has set its own cookies.
 */
class SendEdgeHeaders
{
    public function __construct(
        private readonly CachedPage $cachedPage,
        private readonly Request $request
    ) {
    }

    public function beforeSendHeaders(Response $subject): void
    {
        $ttl = $this->cachedPage->ttl();
        if ($ttl === 0
            || !$this->cachedPage->isShareable()
            || $subject->getHttpResponseCode() != 200
            || !($this->request->isGet() || $this->request->isHead())
        ) {
            return;
        }

        $subject->clearHeader('Set-Cookie');
        $subject->clearHeader('Pragma');
        $subject->clearHeader('Expires');
        $subject->setHeader('Cache-Control', 'public, max-age=0, s-maxage=' . $ttl, true);
        if (!headers_sent()) {
            // PHP's session sets its cookie and no-cache headers with header(),
            // outside the response object.
            header_remove('Set-Cookie');
            header_remove('Pragma');
            header_remove('Expires');
            header_remove('Cache-Control');
        }
    }
}
