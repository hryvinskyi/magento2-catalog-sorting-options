<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\CategorySortBySourceListingSortOptionsPlugin;
use Magento\Catalog\Model\Category\Attribute\Source\Sortby;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Every registered listing sort option is appended to a category's Sort By lists as a label and a
 * value, after the existing options; a code already listed keeps its entry, and a result that is not a list is
 * returned untouched.
 */
class CategorySortBySourceListingSortOptionsPluginTest extends TestCase
{
    /**
     * @var ListingSortOptionsInterface&MockObject
     */
    private $listingSortOptions;

    /**
     * @var Sortby&MockObject
     */
    private $source;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->listingSortOptions = $this->createMock(ListingSortOptionsInterface::class);
        $this->source = $this->createMock(Sortby::class);
    }

    /**
     * @return void
     */
    public function testEveryRegisteredOptionIsAppendedAsALabelAndAValue(): void
    {
        $this->listingSortOptions->expects(self::never())->method('offeredIn');
        $this->listingSortOptions->method('all')->willReturn(['bestsellers' => 'Best Sellers', 'new' => 'New']);

        self::assertSame(
            [
                ['label' => 'Position', 'value' => 'position'],
                ['label' => 'Best Sellers', 'value' => 'bestsellers'],
                ['label' => 'New', 'value' => 'new'],
            ],
            $this->plugin()->afterGetAllOptions($this->source, [['label' => 'Position', 'value' => 'position']])
        );
    }

    /**
     * @return void
     */
    public function testACodeAlreadyListedKeepsItsEntry(): void
    {
        $this->listingSortOptions->method('all')->willReturn(['new' => 'New', 'bestsellers' => 'Best Sellers']);

        self::assertSame(
            [
                ['label' => 'Newness', 'value' => 'new'],
                'not an option',
                ['label' => 'Best Sellers', 'value' => 'bestsellers'],
            ],
            $this->plugin()->afterGetAllOptions(
                $this->source,
                [['label' => 'Newness', 'value' => 'new'], 'not an option']
            )
        );
    }

    /**
     * @return void
     */
    public function testAResultThatIsNotAListIsReturnedUntouched(): void
    {
        $this->listingSortOptions->expects(self::never())->method('all');

        self::assertNull($this->plugin()->afterGetAllOptions($this->source, null));
    }

    /**
     * Build the plugin under test
     *
     * @return CategorySortBySourceListingSortOptionsPlugin
     */
    private function plugin(): CategorySortBySourceListingSortOptionsPlugin
    {
        return new CategorySortBySourceListingSortOptionsPlugin($this->listingSortOptions);
    }
}
