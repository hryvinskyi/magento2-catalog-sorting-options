<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Integration\Plugin;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionCatalogueInterface;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\RegistersListingSortOption;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\ResolvesServices;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\TestListingSortOption;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Attribute\Source\Sortby;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Config\Source\ListSort;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use PHPUnit\Framework\TestCase;

/**
 * The admin can pick every registered listing sort option wherever a sort option is chosen: a category's available
 * and default Sort By, the store's default Sort By, and the sort options it hides, renames and moves. The catalog's
 * own list of sortable options stays untouched outside the storefront.
 *
 * Every scenario runs on a fresh application, because the test option replaces the shared list of listing sort
 * options. No store offers the option unless a scenario says so: the admin lists it all the same.
 */
#[AppArea('adminhtml')]
class AdminListingSortOptionsTest extends TestCase
{
    use ResolvesServices;
    use RegistersListingSortOption;

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testACategorySortByListOffersTheOption(): void
    {
        $this->registerListingSortOption();

        $this->assertContains(
            ['label' => TestListingSortOption::LABEL, 'value' => TestListingSortOption::CODE],
            $this->service(Sortby::class)->getAllOptions()
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testTheStoreDefaultSortByListOffersTheOption(): void
    {
        $this->registerListingSortOption();

        $this->assertContains(
            ['label' => TestListingSortOption::LABEL, 'value' => TestListingSortOption::CODE],
            $this->service(ListSort::class)->toOptionArray()
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testTheSortOptionsTheAdminOverridesIncludeTheOption(): void
    {
        $this->registerListingSortOption();

        $options = $this->service(SortOptionCatalogueInterface::class)->getSortOptions();

        $this->assertSame(TestListingSortOption::LABEL, $options[TestListingSortOption::CODE] ?? null);
        $this->assertArrayHasKey('position', $options);
        $this->assertArrayHasKey('relevance', $options);
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testACategoryThatMakesTheOptionAvailableCanDefaultToIt(): void
    {
        $this->registerListingSortOption();
        $category = $this->service(CategoryFactory::class)->create();
        $category->setData('available_sort_by', ['name', TestListingSortOption::CODE]);
        $category->setData('default_sort_by', TestListingSortOption::CODE);

        $attribute = $this->service(EavConfig::class)->getAttribute(Category::ENTITY, 'default_sort_by');

        $this->assertTrue($attribute->getBackend()->validate($category));
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(TestListingSortOption::ENABLED_PATH, '1', ScopeInterface::SCOPE_STORE, 'default')]
    public function testTheCatalogSortableOptionsStayUntouchedOutsideTheStorefront(): void
    {
        $this->registerListingSortOption();

        $this->assertArrayNotHasKey(
            TestListingSortOption::CODE,
            $this->service(CatalogConfig::class)->getAttributeUsedForSortByArray()
        );
    }
}
