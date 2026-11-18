<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Model\Source\ListingSortOptionsSource;
use PHPUnit\Framework\TestCase;

/**
 * The admin can override every registered listing sort option, offered in a store or not.
 */
class ListingSortOptionsSourceTest extends TestCase
{
    /**
     * @return void
     */
    public function testOffersEveryRegisteredListingSortOption(): void
    {
        $listingSortOptions = $this->createMock(ListingSortOptionsInterface::class);
        $listingSortOptions->method('all')->willReturn(['bestsellers' => 'Best Sellers', 'new' => 'New']);
        $listingSortOptions->expects(self::never())->method('offeredIn');

        self::assertSame(
            ['bestsellers' => 'Best Sellers', 'new' => 'New'],
            (new ListingSortOptionsSource($listingSortOptions))->getSortOptions()
        );
    }
}
