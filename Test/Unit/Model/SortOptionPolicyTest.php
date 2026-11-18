<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSortingOptions\Exception\DuplicateSortOptionOverrideException;
use Hryvinskyi\CatalogSortingOptions\Model\Data\SortOptionOverride;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionPolicy;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A store's policy hides, relabels and moves the options a listing offers, never empties a listing, ignores
 * codes the listing does not offer, gives the same list when applied to its own output, and names the option
 * that replaces a hidden default order.
 */
class SortOptionPolicyTest extends TestCase
{
    private const CATEGORY_OPTIONS = [
        'position' => 'Position',
        'name' => 'Product Name',
        'price' => 'Price',
    ];

    /**
     * @return void
     */
    public function testWithoutOverridesTheOptionsComeBackExactlyAsGiven(): void
    {
        $label = new Phrase('Price');
        $options = ['position' => 'Position', 7 => null, 'price' => $label];

        $applied = (new SortOptionPolicy())->apply($options);

        self::assertSame($options, $applied);
        self::assertSame($label, $applied['price']);
    }

    /**
     * @return void
     */
    public function testRefusesTwoOverridesForOneCodeAndNamesIt(): void
    {
        try {
            $this->policy(new SortOptionOverride('price', false), new SortOptionOverride(' price ', true, 'Cost'));
            self::fail('Two overrides for one code were accepted.');
        } catch (DuplicateSortOptionOverrideException $exception) {
            self::assertSame('price', $exception->getSortOptionCode());
            self::assertStringContainsString('"price"', $exception->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testRefusesAnItemThatIsNotAnOverride(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort option override "hide_price" must implement');

        new SortOptionPolicy(['hide_price' => ['code' => 'price', 'shown' => false]]);
    }

    /**
     * @return void
     */
    public function testAHiddenCodeIsRemovedAndTheOthersKeepTheirOrder(): void
    {
        $options = ['position' => 'Position', 'name' => 'Name', 'price' => 'Price', 'rating' => 'Rating'];

        $applied = $this->policy($this->hidden('name'))->apply($options);

        self::assertSame(['position' => 'Position', 'price' => 'Price', 'rating' => 'Rating'], $applied);
    }

    /**
     * @return void
     */
    public function testANonBlankLabelReplacesTheOptionLabel(): void
    {
        $applied = $this->policy(new SortOptionOverride('position', true, 'Recommended'))
            ->apply(self::CATEGORY_OPTIONS);

        self::assertSame(
            ['position' => 'Recommended', 'name' => 'Product Name', 'price' => 'Price'],
            $applied
        );
    }

    /**
     * @return void
     */
    public function testAnOverrideWithABlankLabelKeepsTheCurrentLabel(): void
    {
        $label = new Phrase('Price');

        $applied = $this->policy(new SortOptionOverride('price', true, '   '))->apply(['price' => $label]);

        self::assertSame($label, $applied['price']);
    }

    /**
     * @return void
     */
    public function testUntouchedLabelsOfAnyTypeKeepTheirValueAndTypeAndReplacedOnesAreStrings(): void
    {
        $phrase = new Phrase('Position');
        $object = new \stdClass();
        $options = ['position' => $phrase, 'name' => $object, 'price' => 42, 'rating' => null, 'newest' => 'New'];

        $applied = $this->policy(
            new SortOptionOverride('price', true, 'Cost'),
            new SortOptionOverride('rating', true, 'Top rated')
        )->apply($options);

        self::assertSame($phrase, $applied['position']);
        self::assertSame($object, $applied['name']);
        self::assertSame('Cost', $applied['price']);
        self::assertSame('Top rated', $applied['rating']);
        self::assertSame('New', $applied['newest']);
        self::assertSame(['position', 'name', 'price', 'rating', 'newest'], array_keys($applied));
    }

    /**
     * @return void
     */
    public function testANumericOptionKeyMatchesTheOverrideForItsCode(): void
    {
        $options = [5 => 'Five', 'name' => 'Name', 12 => 'Twelve'];

        $applied = $this->policy($this->hidden('5'), new SortOptionOverride('12', true, 'Dozen'))->apply($options);

        self::assertSame(['name' => 'Name', 12 => 'Dozen'], $applied);
    }

    /**
     * @return void
     */
    public function testWhenHidingWouldEmptyTheListingEveryOptionStaysRelabelledAndMoved(): void
    {
        $applied = $this->policy(
            $this->hidden('position'),
            new SortOptionOverride('name', false, 'A to Z'),
            new SortOptionOverride('price', false, null, 1)
        )->apply(self::CATEGORY_OPTIONS);

        self::assertSame(['price' => 'Price', 'position' => 'Position', 'name' => 'A to Z'], $applied);
    }

    /**
     * @return void
     */
    public function testHidingEveryOptionOfAnotherListingDoesNotEmptyItEither(): void
    {
        $policy = $this->policy($this->hidden('position'), $this->hidden('relevance'));

        self::assertSame(['relevance' => 'Relevance'], $policy->apply(['relevance' => 'Relevance']));
        self::assertSame(['name' => 'Name'], $policy->apply(['position' => 'Position', 'name' => 'Name']));
    }

    /**
     * @return void
     */
    public function testAnEmptyListingStaysEmpty(): void
    {
        self::assertSame([], $this->policy($this->hidden('position'), $this->placed('name', 1))->apply([]));
    }

    /**
     * @return void
     */
    public function testOverridesForCodesTheListingDoesNotOfferAreIgnored(): void
    {
        $applied = $this->policy(
            $this->hidden('relevance'),
            new SortOptionOverride('bestsellers', true, 'Popular', 1)
        )->apply(self::CATEGORY_OPTIONS);

        self::assertSame(self::CATEGORY_OPTIONS, $applied);
    }

    /**
     * @return void
     */
    public function testAnOptionWithAPlaceIsPutAtThatPlaceAndTheOthersKeepTheirOrder(): void
    {
        $options = ['position' => 'P', 'name' => 'N', 'price' => 'Pr', 'rating' => 'R', 'newest' => 'Nw'];

        $applied = $this->policy($this->placed('name', 3))->apply($options);

        self::assertSame(
            ['position' => 'P', 'price' => 'Pr', 'name' => 'N', 'rating' => 'R', 'newest' => 'Nw'],
            $applied
        );
    }

    /**
     * @return void
     */
    public function testTheFirstPlaceMovesAnOptionToTheTop(): void
    {
        $applied = $this->policy($this->placed('price', 1))->apply(self::CATEGORY_OPTIONS);

        self::assertSame(['price', 'position', 'name'], array_keys($applied));
    }

    /**
     * @return void
     */
    public function testOptionsWithoutAPlaceFillTheGapsBetweenPlacedOnes(): void
    {
        $options = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'];

        $applied = $this->policy($this->placed('c', 1), $this->placed('a', 4))->apply($options);

        self::assertSame(['c', 'b', 'd', 'a', 'e'], array_keys($applied));
    }

    /**
     * @return void
     */
    public function testPlacesBeyondTheListPutTheOptionsAtTheEndInPlaceOrder(): void
    {
        $options = ['position' => 'Position', 'name' => 'Name', 'price' => 'Price', 'rating' => 'Rating'];

        $applied = $this->policy($this->placed('price', 10), $this->placed('name', 9))->apply($options);

        self::assertSame(['position', 'rating', 'name', 'price'], array_keys($applied));
    }

    /**
     * @return void
     */
    public function testOptionsSharingOnePlaceKeepTheirCurrentOrder(): void
    {
        $options = ['position' => 'Position', 'price' => 'Price', 'name' => 'Name'];

        $applied = $this->policy($this->placed('name', 1), $this->placed('price', 1))->apply($options);

        self::assertSame(['price', 'name', 'position'], array_keys($applied));
    }

    /**
     * @return void
     */
    public function testPlacesCountOnTheOptionsLeftAfterHiding(): void
    {
        $options = ['position' => 'Position', 'name' => 'Name', 'price' => 'Price', 'rating' => 'Rating'];

        $applied = $this->policy($this->hidden('position'), $this->placed('rating', 2))->apply($options);

        self::assertSame(['name' => 'Name', 'rating' => 'Rating', 'price' => 'Price'], $applied);
    }

    /**
     * @return void
     */
    public function testAMovedOptionKeepsItsLabelUntouched(): void
    {
        $label = new Phrase('Price');

        $applied = $this->policy($this->placed('price', 1))->apply(['name' => 'Name', 'price' => $label]);

        self::assertSame(['price', 'name'], array_keys($applied));
        self::assertSame($label, $applied['price']);
    }

    /**
     * @param list<SortOptionOverride> $overrides The store's overrides
     * @param array<array-key,mixed> $options Options a listing offers
     * @return void
     * @dataProvider idempotencyProvider
     */
    #[DataProvider('idempotencyProvider')]
    public function testApplyingThePolicyToItsOwnOutputChangesNothing(array $overrides, array $options): void
    {
        $policy = new SortOptionPolicy($overrides);
        $once = $policy->apply($options);

        self::assertSame($once, $policy->apply($once));
    }

    /**
     * @return array<string, array{list<SortOptionOverride>, array<array-key, mixed>}>
     */
    public static function idempotencyProvider(): array
    {
        $options = [
            'position' => 'Position',
            'name' => 'Name',
            'price' => 'Price',
            'rating' => 'Rating',
            'newest' => 'Newest',
        ];

        return [
            'hide' => [[new SortOptionOverride('position', false)], $options],
            'rename' => [[new SortOptionOverride('name', true, 'A to Z')], $options],
            'place inside the list' => [
                [new SortOptionOverride('rating', true, null, 2), new SortOptionOverride('name', true, null, 4)],
                $options,
            ],
            'place beyond the list' => [
                [new SortOptionOverride('position', true, null, 20), new SortOptionOverride('price', true, null, 9)],
                $options,
            ],
            'shared place' => [
                [new SortOptionOverride('newest', true, null, 2), new SortOptionOverride('name', true, null, 2)],
                $options,
            ],
            'hide, rename and move' => [
                [
                    new SortOptionOverride('position', false),
                    new SortOptionOverride('price', true, 'Cost', 1),
                    new SortOptionOverride('newest', true, null, 3),
                ],
                $options,
            ],
            'hide everything' => [
                [
                    new SortOptionOverride('position', false, null, 3),
                    new SortOptionOverride('name', false, 'A to Z'),
                    new SortOptionOverride('price', false),
                ],
                ['position' => 'Position', 'name' => 'Name', 'price' => 'Price'],
            ],
        ];
    }

    /**
     * Code that reads the list, drops one code, appends others and sets it back gets it filtered once more.
     *
     * @return void
     */
    public function testAListReadEditedAndSetBackIsStillHiddenRenamedAndMoved(): void
    {
        $policy = $this->policy(
            $this->hidden('position'),
            new SortOptionOverride('name', true, 'A to Z'),
            $this->placed('relevance', 1)
        );

        $read = $policy->apply(self::CATEGORY_OPTIONS);
        unset($read['price']);
        $read['position'] = 'Position';
        $read['relevance'] = 'Relevance';

        self::assertSame(['relevance' => 'Relevance', 'name' => 'A to Z'], $policy->apply($read));
    }

    /**
     * @return void
     */
    public function testNoReplacementForADefaultThePolicyDoesNotHide(): void
    {
        $policy = $this->policy($this->hidden('position'), new SortOptionOverride('name', true, 'A to Z'));
        $shown = $policy->apply(self::CATEGORY_OPTIONS);

        self::assertNull($policy->replacementForDefault('name', $shown));
        self::assertNull($policy->replacementForDefault('price', $shown));
    }

    /**
     * @return void
     */
    public function testNoReplacementForAHiddenDefaultTheListingKeptBecauseHidingWouldHaveEmptiedIt(): void
    {
        $policy = $this->policy($this->hidden('relevance'));
        $shown = $policy->apply(['relevance' => 'Relevance']);

        self::assertNull($policy->replacementForDefault('relevance', $shown));
    }

    /**
     * @return void
     */
    public function testNoReplacementWhenNoOptionIsShown(): void
    {
        self::assertNull($this->policy($this->hidden('position'))->replacementForDefault('position', []));
    }

    /**
     * @return void
     */
    public function testAHiddenDefaultIsReplacedByTheFirstShownOptionAfterMoving(): void
    {
        $policy = $this->policy($this->hidden('position'), $this->placed('price', 1));
        $shown = $policy->apply(self::CATEGORY_OPTIONS);

        self::assertSame('price', $policy->replacementForDefault('position', $shown));
    }

    /**
     * @return void
     */
    public function testANumericFirstShownCodeIsGivenAsAString(): void
    {
        $policy = $this->policy($this->hidden('position'));

        $shown = $policy->apply(['position' => 'Position', 7 => 'Seven']);

        self::assertSame('7', $policy->replacementForDefault('position', $shown));
    }

    /**
     * @return void
     */
    public function testAPolicyWithoutOverridesHidesNothing(): void
    {
        $policy = new SortOptionPolicy();

        self::assertFalse($policy->hidesAnything());
        self::assertFalse($policy->hides('position'));
    }

    /**
     * @return void
     */
    public function testOverridesThatOnlyRenameOrMoveHideNothing(): void
    {
        $policy = $this->policy(new SortOptionOverride('position', true, 'Recommended'), $this->placed('name', 1));

        self::assertFalse($policy->hidesAnything());
        self::assertFalse($policy->hides('position'));
        self::assertFalse($policy->hides('name'));
    }

    /**
     * @return void
     */
    public function testAHiddenCodeIsReportedAsHidden(): void
    {
        $policy = $this->policy(new SortOptionOverride('name', true, 'A to Z'), $this->hidden('position'));

        self::assertTrue($policy->hidesAnything());
        self::assertTrue($policy->hides('position'));
        self::assertFalse($policy->hides('name'));
        self::assertFalse($policy->hides('price'));
    }

    /**
     * Build a policy from overrides
     *
     * @param SortOptionOverride ...$overrides The store's overrides
     * @return SortOptionPolicy
     */
    private function policy(SortOptionOverride ...$overrides): SortOptionPolicy
    {
        return new SortOptionPolicy(array_values($overrides));
    }

    /**
     * Build an override that hides a code
     *
     * @param string $code Sort option code
     * @return SortOptionOverride
     */
    private function hidden(string $code): SortOptionOverride
    {
        return new SortOptionOverride($code, false);
    }

    /**
     * Build an override that moves a code to a place in list
     *
     * @param string $code Sort option code
     * @param int $position Place in list
     * @return SortOptionOverride
     */
    private function placed(string $code, int $position): SortOptionOverride
    {
        return new SortOptionOverride($code, true, null, $position);
    }
}
