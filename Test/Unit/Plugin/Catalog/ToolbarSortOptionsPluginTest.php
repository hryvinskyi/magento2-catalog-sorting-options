<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\ToolbarSortOptionsPlugin;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The toolbar's available orders go through the policy of the store being served, and only then.
 */
class ToolbarSortOptionsPluginTest extends TestCase
{
    /**
     * @var SortOptionPolicyResolverInterface&MockObject
     */
    private $resolver;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var Toolbar&MockObject
     */
    private $toolbar;

    /**
     * @var ToolbarSortOptionsPlugin
     */
    private ToolbarSortOptionsPlugin $plugin;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(SortOptionPolicyResolverInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->toolbar = $this->createMock(Toolbar::class);
        $this->plugin = new ToolbarSortOptionsPlugin($this->resolver, $this->storeManager);
    }

    /**
     * @param mixed $result Value the toolbar returned
     * @return void
     * @dataProvider unusableResultProvider
     */
    #[DataProvider('unusableResultProvider')]
    public function testResultThatIsNotAFilledArrayIsReturnedBeforeTheStoreIsAsked(mixed $result): void
    {
        $this->storeManager->expects(self::never())->method('getStore');
        $this->resolver->expects(self::never())->method('forStore');

        self::assertSame($result, $this->plugin->afterGetAvailableOrders($this->toolbar, $result));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableResultProvider(): array
    {
        return [
            'empty array' => [[]],
            'null' => [null],
            'string' => ['position'],
        ];
    }

    /**
     * @return void
     */
    public function testPolicyOfTheCurrentStoreIsAppliedAndItsOutputReturned(): void
    {
        $options = ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price'];
        $applied = ['name' => 'Product Name', 'price' => 'Cost'];

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(7);
        $this->storeManager->expects(self::once())->method('getStore')->willReturn($store);

        $policy = $this->createMock(SortOptionPolicyInterface::class);
        $policy->expects(self::once())->method('apply')->with($options)->willReturn($applied);
        $this->resolver->expects(self::once())->method('forStore')->with(7)->willReturn($policy);

        self::assertSame($applied, $this->plugin->afterGetAvailableOrders($this->toolbar, $options));
    }
}
