<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionInterface;
use Hryvinskyi\CatalogSortingOptions\Model\ListingSortOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The registered options are listed by code in pool order; a store gets the ones it offers, collected once per store;
 * a pool item that is not an option, or a code registered twice, fails the construction and is named.
 */
class ListingSortOptionsTest extends TestCase
{
    /**
     * @return void
     */
    public function testWithoutOptionsNothingIsOfferedOrListed(): void
    {
        $options = new ListingSortOptions();

        self::assertSame([], $options->offeredIn(1));
        self::assertSame([], $options->all());
        self::assertNull($options->defaultDirectionOf('bestsellers'));
    }

    /**
     * @return void
     */
    public function testAStoreGetsTheOptionsItOffersInPoolOrder(): void
    {
        $options = new ListingSortOptions([
            'wished' => $this->option('wished', 'Now in Wishlists', [3]),
            'bestsellers' => $this->option('bestsellers', 'Best Sellers', [3, 4]),
            'saving' => $this->option('saving', 'Biggest Saving', [4]),
        ]);

        self::assertSame(['wished' => 'Now in Wishlists', 'bestsellers' => 'Best Sellers'], $options->offeredIn(3));
        self::assertSame(['bestsellers' => 'Best Sellers', 'saving' => 'Biggest Saving'], $options->offeredIn(4));
        self::assertSame([], $options->offeredIn(5));
    }

    /**
     * @return void
     */
    public function testTheOfferedOptionsAreCollectedOncePerStore(): void
    {
        $option = $this->createMock(ListingSortOptionInterface::class);
        $option->method('getCode')->willReturn('bestsellers');
        $option->method('getLabel')->willReturn('Best Sellers');
        $option->expects(self::exactly(2))
            ->method('isOffered')
            ->willReturnCallback(static fn (int $storeId): bool => $storeId === 3);
        $options = new ListingSortOptions(['bestsellers' => $option]);

        $options->offeredIn(3);
        $options->offeredIn(3);
        $options->offeredIn(4);

        self::assertSame(['bestsellers' => 'Best Sellers'], $options->offeredIn(3));
        self::assertSame([], $options->offeredIn(4));
    }

    /**
     * @return void
     */
    public function testEveryOptionIsListedWhetherAnyStoreOffersItOrNot(): void
    {
        $options = new ListingSortOptions([
            'bestsellers' => $this->option('bestsellers', 'Best Sellers', []),
            'new' => $this->option('new', 'New', [3]),
        ]);

        self::assertSame(['bestsellers' => 'Best Sellers', 'new' => 'New'], $options->all());
    }

    /**
     * @return void
     */
    public function testTheDefaultDirectionOfAnOptionIsItsOwnAndOtherCodesHaveNone(): void
    {
        $options = new ListingSortOptions([
            'bestsellers' => $this->option('bestsellers', 'Best Sellers', [], SortDirection::Descending),
            'oldest' => $this->option('oldest', 'Oldest', [], SortDirection::Ascending),
        ]);

        self::assertSame(SortDirection::Descending, $options->defaultDirectionOf('bestsellers'));
        self::assertSame(SortDirection::Ascending, $options->defaultDirectionOf('oldest'));
        self::assertNull($options->defaultDirectionOf('price'));
        self::assertNull($options->defaultDirectionOf('Bestsellers'));
    }

    /**
     * @return void
     */
    public function testAPoolItemThatIsNotAnOptionIsRefusedAndNamed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listing sort option "broken" must implement');

        new ListingSortOptions([
            'bestsellers' => $this->option('bestsellers', 'Best Sellers', []),
            'broken' => new \stdClass(),
        ]);
    }

    /**
     * @return void
     */
    public function testACodeRegisteredTwiceIsRefusedAndNamed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listing sort option code "bestsellers" is registered more than once.');

        new ListingSortOptions([
            'bestsellers' => $this->option('bestsellers', 'Best Sellers', []),
            'top_sellers' => $this->option('bestsellers', 'Top Sellers', []),
        ]);
    }

    /**
     * Create a listing sort option double
     *
     * @param string $code Sort code
     * @param string $label Label
     * @param list<int> $offeredIn Stores that offer it
     * @param SortDirection $direction Default direction
     * @return ListingSortOptionInterface&MockObject
     */
    private function option(
        string $code,
        string $label,
        array $offeredIn,
        SortDirection $direction = SortDirection::Descending
    ): ListingSortOptionInterface {
        $option = $this->createMock(ListingSortOptionInterface::class);
        $option->method('getCode')->willReturn($code);
        $option->method('getLabel')->willReturn($label);
        $option->method('getDefaultDirection')->willReturn($direction);
        $option->method('isOffered')->willReturnCallback(
            static fn (int $storeId): bool => in_array($storeId, $offeredIn, true)
        );

        return $option;
    }
}
