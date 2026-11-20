<?php
/**
 * Copyright Spectivis. Licensed under OSL-3.0 and AFL-3.0, as Mage-OS.
 */
declare(strict_types=1);

namespace Spectivis\DemoCatalog\Model\Setup;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Spectivis\DemoCatalog\Model\Vocabulary;

/**
 * Makes the category tree under the default store's root category: the
 * departments and their subcategories in the menu, the leaf categories
 * below them. Categories are found again by name and parent, so it adds only
 * what is missing.
 */
class CategoryInstaller
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array{ids: list<int>, added: int} category id of each leaf in
     *     Vocabulary::leaves() order, and how many categories were made
     */
    public function install(): array
    {
        $rootId = (int) $this->storeManager->getDefaultStoreView()->getRootCategoryId();
        $existing = $this->existing();
        $added = 0;
        $ids = [];
        $currentStore = $this->storeManager->getStore()->getId();
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        try {
            $position = 0;
            foreach (Vocabulary::leaves() as $leaf) {
                $departmentId = $this->ensure($existing, $rootId, $leaf['department'], true, ++$position, $added);
                $subId = $this->ensure($existing, $departmentId, $leaf['sub'], true, $position, $added);
                $ids[] = $this->ensure($existing, $subId, $leaf['leaf'], false, $position, $added);
            }
        } finally {
            $this->storeManager->setCurrentStore($currentStore);
        }

        return ['ids' => $ids, 'added' => $added];
    }

    /**
     * @param array<string, int> $existing parent id and name => category id
     */
    private function ensure(array &$existing, int $parentId, string $name, bool $inMenu, int $position, int &$added): int
    {
        $key = $parentId . '|' . $name;
        if (isset($existing[$key])) {
            return $existing[$key];
        }
        $category = $this->categoryFactory->create();
        $category->setStoreId(Store::DEFAULT_STORE_ID);
        $category->setParentId($parentId);
        $category->setName($name);
        $category->setIsActive(true);
        $category->setIncludeInMenu($inMenu);
        $category->setIsAnchor(true);
        $category->setPosition($position);
        $category->setData('display_mode', 'PRODUCTS');
        $category->setUrlKey(Vocabulary::slug($name));
        $category = $this->categoryRepository->save($category);
        $added++;

        return $existing[$key] = (int) $category->getId();
    }

    /**
     * @return array<string, int>
     */
    private function existing(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);
        $collection->addAttributeToSelect('name');
        $map = [];
        foreach ($collection as $category) {
            $map[$category->getParentId() . '|' . $category->getName()] = (int) $category->getId();
        }

        return $map;
    }
}
