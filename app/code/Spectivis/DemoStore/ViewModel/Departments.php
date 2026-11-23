<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoStore\ViewModel;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The store's top-level categories for the home page tiles.
 */
class Departments implements ArgumentInterface
{
    /** URL keys the Spectivis/demo theme has a photo for, in images/departments. */
    private const PHOTOS = [
        'women', 'men', 'kids', 'shoes', 'bags-and-accessories', 'home-and-living', 'outdoor', 'sports-and-fitness',
    ];

    /** URL keys shown first on the home page, in this order; the rest follow by position. */
    private const LEADING = ['home-and-living', 'outdoor'];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return list<array{name: string, url: string, image: ?string}>
     */
    public function list(): array
    {
        $store = $this->storeManager->getStore();
        $collection = $this->collectionFactory->create()
            ->setStoreId((int) $store->getId())
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('parent_id', (int) $store->getRootCategoryId())
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->addUrlRewriteToResult()
            ->setOrder('position', 'ASC')
            ->setPageSize(8);

        $departments = [];
        foreach ($collection as $category) {
            $key = (string) $category->getUrlKey();
            $departments[] = [
                'key' => $key,
                'name' => (string) $category->getName(),
                'url' => (string) $category->getUrl(),
                'image' => in_array($key, self::PHOTOS, true) ? 'images/departments/' . $key . '.jpg' : null,
            ];
        }

        usort($departments, fn (array $a, array $b): int => $this->rank($a['key']) <=> $this->rank($b['key']));

        return array_map(fn (array $department): array => array_diff_key($department, ['key' => true]), $departments);
    }

    private function rank(string $key): int
    {
        $index = array_search($key, self::LEADING, true);

        return $index === false ? count(self::LEADING) : $index;
    }
}
