<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model\Setup;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;

/**
 * Puts a grid of products on the home page, which in a fresh store is an
 * empty CMS page. A home page someone has edited is left alone.
 */
class HomePageInstaller
{
    private const MARKER = 'spectivis-demo-catalog';

    private const CONTENT = <<<'HTML'
<div class="spectivis-demo-catalog">
<h2>New in store</h2>
{{widget type="Magento\CatalogWidget\Block\Product\ProductsList" show_pager="0" products_count="12" template="Magento_CatalogWidget::product/widget/content/grid.phtml" conditions_encoded="^[`1`:^[`type`:`Magento||CatalogWidget||Model||Rule||Condition||Combine`,`aggregator`:`all`,`value`:`1`,`new_child`:``^]^]"}}
</div>
HTML;

    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    /**
     * @return bool whether the page was changed
     */
    public function install(): bool
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return false;
        }
        $content = trim(strip_tags((string) $page->getContent()));
        if (str_contains((string) $page->getContent(), self::MARKER) || ($content !== '' && $content !== 'CMS homepage content goes here.')) {
            return false;
        }
        $page->setContent(self::CONTENT);
        $this->pageRepository->save($page);

        return true;
    }
}
