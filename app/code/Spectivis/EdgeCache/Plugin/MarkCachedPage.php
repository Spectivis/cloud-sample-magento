<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\EdgeCache\Plugin;

use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\PageCache\NotCacheableInterface;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\Response\Http as Response;
use Spectivis\EdgeCache\Model\CachedPage;

/**
 * Notes when the built-in full page cache serves a page (a hit) or is about to
 * store one (a cacheable miss), using the same test as Kernel::process(), and
 * whether the visitor has the X-Magento-Vary cookie.
 */
class MarkCachedPage
{
    public function __construct(
        private readonly CachedPage $cachedPage,
        private readonly Request $request
    ) {
    }

    /**
     * Load runs first, before rendering clears the request's cookies.
     */
    public function beforeLoad(Kernel $subject): void
    {
        $this->cachedPage->setVaries((bool) $this->request->getCookie(Response::COOKIE_VARY_STRING));
    }

    public function afterLoad(Kernel $subject, $result)
    {
        if ($result instanceof Response && $result->getHttpResponseCode() == 200) {
            $this->cachedPage->mark();
        }
        return $result;
    }

    public function beforeProcess(Kernel $subject, Response $response): void
    {
        $cacheControl = $response->getHeader('Cache-Control');
        if ($cacheControl
            && preg_match('/public.*s-maxage=\d+/', (string) $cacheControl->getFieldValue())
            && $response->getHttpResponseCode() == 200
            && !$response instanceof NotCacheableInterface
            && ($this->request->isGet() || $this->request->isHead())
        ) {
            $this->cachedPage->mark();
        }
    }
}
