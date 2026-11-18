<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Model\DefaultDirectionResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A listing sort option opens in its own direction; otherwise every order of a listing whose hidden default order is
 * replaced opens ascending; otherwise there is no answer. The first rule wins, and a later rule is never evaluated
 * once one has.
 */
class DefaultDirectionResolverTest extends TestCase
{
    /**
     * Options the listing shows after the policy hid relevance
     */
    private const SHOWN = ['name' => 'Product Name', 'price' => 'Price'];

    /**
     * @var ListingSortOptionsInterface&MockObject
     */
    private $listingSortOptions;

    /**
     * @var SortOptionPolicyInterface&MockObject
     */
    private $policy;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->listingSortOptions = $this->createMock(ListingSortOptionsInterface::class);
        $this->listingSortOptions->method('defaultDirectionOf')->willReturnMap([
            ['bestsellers', SortDirection::Descending],
            ['oldest', SortDirection::Ascending],
            ['name', null],
            ['price', null],
        ]);
        $this->policy = $this->createMock(SortOptionPolicyInterface::class);
    }

    /**
     * @return void
     */
    public function testAListingSortOptionGetsItsOwnDirectionWithoutAskingThePolicy(): void
    {
        $this->policy->expects(self::never())->method('hides');
        $this->policy->expects(self::never())->method('replacementForDefault');

        self::assertSame(
            SortDirection::Descending,
            $this->resolver()->forOrder('bestsellers', $this->policy, 'relevance', self::SHOWN)
        );
        self::assertSame(
            SortDirection::Ascending,
            $this->resolver()->forOrder('oldest', $this->policy, null, [])
        );
    }

    /**
     * @return void
     */
    public function testAListingSortOptionWinsOverTheHiddenDefaultItReplaces(): void
    {
        $this->policy->method('hides')->willReturn(true);
        $this->policy->method('replacementForDefault')->willReturn('bestsellers');

        self::assertSame(
            SortDirection::Descending,
            $this->resolver()->forOrder('bestsellers', $this->policy, 'relevance', ['bestsellers' => 'Best Sellers'])
        );
    }

    /**
     * @return void
     */
    public function testTheOptionReplacingAHiddenDefaultOrderOpensAscending(): void
    {
        $this->policy->expects(self::once())->method('hides')->with('relevance')->willReturn(true);
        $this->policy->expects(self::once())
            ->method('replacementForDefault')
            ->with('relevance', self::SHOWN)
            ->willReturn('name');

        self::assertSame(
            SortDirection::Ascending,
            $this->resolver()->forOrder('name', $this->policy, 'relevance', self::SHOWN)
        );
    }

    /**
     * @return void
     */
    public function testAnOrderOtherThanTheReplacementOpensAscendingToo(): void
    {
        $this->policy->method('hides')->willReturn(true);
        $this->policy->method('replacementForDefault')->willReturn('name');

        self::assertSame(
            SortDirection::Ascending,
            $this->resolver()->forOrder('price', $this->policy, 'relevance', self::SHOWN)
        );
    }

    /**
     * @return void
     */
    public function testAHiddenDefaultThatIsNotReplacedGivesNoAnswer(): void
    {
        $this->policy->method('hides')->willReturn(true);
        $this->policy->method('replacementForDefault')->willReturn(null);

        self::assertNull(
            $this->resolver()->forOrder('name', $this->policy, 'relevance', ['relevance' => 'Relevance'])
        );
    }

    /**
     * @return void
     */
    public function testADefaultThePolicyShowsGivesNoAnswerWithoutLookingForAReplacement(): void
    {
        $this->policy->expects(self::once())->method('hides')->with('position')->willReturn(false);
        $this->policy->expects(self::never())->method('replacementForDefault');

        self::assertNull($this->resolver()->forOrder('name', $this->policy, 'position', self::SHOWN));
    }

    /**
     * @return void
     */
    public function testNoKnownDefaultOrderGivesNoAnswerWithoutAskingThePolicy(): void
    {
        $this->policy->expects(self::never())->method('hides');
        $this->policy->expects(self::never())->method('replacementForDefault');

        self::assertNull($this->resolver()->forOrder('name', $this->policy, null, self::SHOWN));
    }

    /**
     * Build the resolver under test
     *
     * @return DefaultDirectionResolver
     */
    private function resolver(): DefaultDirectionResolver
    {
        return new DefaultDirectionResolver($this->listingSortOptions);
    }
}
