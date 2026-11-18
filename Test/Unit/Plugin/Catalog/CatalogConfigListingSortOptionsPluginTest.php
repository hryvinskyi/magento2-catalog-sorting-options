<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\CatalogConfigListingSortOptionsPlugin;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The listing sort options the current store offers are appended to the catalog's sortable options; an existing
 * entry of the same code is kept, and a result that is not a list is returned before the store is asked.
 */
class CatalogConfigListingSortOptionsPluginTest extends TestCase
{
    /**
     * @var ListingSortOptionsInterface&MockObject
     */
    private $listingSortOptions;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var CatalogConfig&MockObject
     */
    private $catalogConfig;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->listingSortOptions = $this->createMock(ListingSortOptionsInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->catalogConfig = $this->createMock(CatalogConfig::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn('4');
        $this->storeManager->method('getStore')->willReturn($store);
    }

    /**
     * @return void
     */
    public function testTheOptionsTheStoreOffersAreAppendedAfterTheCatalogOnes(): void
    {
        $this->listingSortOptions->expects(self::once())
            ->method('offeredIn')
            ->with(4)
            ->willReturn(['bestsellers' => 'Best Sellers', 'new' => 'New']);

        self::assertSame(
            ['position' => 'Position', 'name' => 'Product Name', 'bestsellers' => 'Best Sellers', 'new' => 'New'],
            $this->plugin()->afterGetAttributeUsedForSortByArray(
                $this->catalogConfig,
                ['position' => 'Position', 'name' => 'Product Name']
            )
        );
    }

    /**
     * @return void
     */
    public function testAnEntryTheCatalogAlreadyHoldsKeepsItsLabelAndPlace(): void
    {
        $this->listingSortOptions->method('offeredIn')->willReturn(['new' => 'New', 'bestsellers' => 'Best Sellers']);

        self::assertSame(
            ['new' => 'Newness', 'position' => 'Position', 'bestsellers' => 'Best Sellers'],
            $this->plugin()->afterGetAttributeUsedForSortByArray(
                $this->catalogConfig,
                ['new' => 'Newness', 'position' => 'Position']
            )
        );
    }

    /**
     * @return void
     */
    public function testTheListIsReturnedAsItIsWhenTheStoreOffersNothing(): void
    {
        $this->listingSortOptions->method('offeredIn')->willReturn([]);

        self::assertSame(
            ['position' => 'Position'],
            $this->plugin()->afterGetAttributeUsedForSortByArray($this->catalogConfig, ['position' => 'Position'])
        );
    }

    /**
     * @return void
     */
    public function testAResultThatIsNotAListIsReturnedBeforeTheStoreIsAsked(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects(self::never())->method('getStore');
        $this->listingSortOptions->expects(self::never())->method('offeredIn');
        $plugin = new CatalogConfigListingSortOptionsPlugin($this->listingSortOptions, $storeManager);

        self::assertNull($plugin->afterGetAttributeUsedForSortByArray($this->catalogConfig, null));
    }

    /**
     * Build the plugin under test
     *
     * @return CatalogConfigListingSortOptionsPlugin
     */
    private function plugin(): CatalogConfigListingSortOptionsPlugin
    {
        return new CatalogConfigListingSortOptionsPlugin($this->listingSortOptions, $this->storeManager);
    }
}
