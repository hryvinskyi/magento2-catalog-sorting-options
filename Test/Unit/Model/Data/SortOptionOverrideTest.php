<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model\Data;

use Hryvinskyi\CatalogSortingOptions\Model\Data\SortOptionOverride;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An override keeps a trimmed, non-blank code, treats a blank label as "keep the current label", accepts a place
 * in list that is empty or at least 1, and is built from the same named keys the generated factory receives.
 */
class SortOptionOverrideTest extends TestCase
{
    /**
     * @return void
     */
    public function testHoldsTheValuesItWasGiven(): void
    {
        $override = new SortOptionOverride('price', false, 'Cost', 2);

        self::assertSame('price', $override->getCode());
        self::assertFalse($override->isShown());
        self::assertSame('Cost', $override->getLabel());
        self::assertSame(2, $override->getPosition());
    }

    /**
     * @return void
     */
    public function testWithoutLabelAndPlaceKeepsTheCurrentLabelAndPlace(): void
    {
        $override = new SortOptionOverride('name', true);

        self::assertTrue($override->isShown());
        self::assertNull($override->getLabel());
        self::assertNull($override->getPosition());
    }

    /**
     * @return void
     */
    public function testTrimsTheCodeAndTheLabel(): void
    {
        $override = new SortOptionOverride("  position\t", true, '  Recommended  ');

        self::assertSame('position', $override->getCode());
        self::assertSame('Recommended', $override->getLabel());
    }

    /**
     * @param string $label A label with nothing in it
     * @return void
     * @dataProvider blankProvider
     */
    #[DataProvider('blankProvider')]
    public function testABlankLabelKeepsTheCurrentLabel(string $label): void
    {
        self::assertNull((new SortOptionOverride('name', true, $label))->getLabel());
    }

    /**
     * @param string $code A code with nothing in it
     * @return void
     * @dataProvider blankProvider
     */
    #[DataProvider('blankProvider')]
    public function testRefusesACodeWithNothingInIt(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the code is blank');

        new SortOptionOverride($code, true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankProvider(): array
    {
        return [
            'empty' => [''],
            'blank' => [" \t\n "],
        ];
    }

    /**
     * @return void
     */
    public function testAcceptsTheFirstPlace(): void
    {
        self::assertSame(1, (new SortOptionOverride('name', true, null, 1))->getPosition());
    }

    /**
     * @param int $position A place before the first one
     * @return void
     * @dataProvider placeBeforeTheFirstProvider
     */
    #[DataProvider('placeBeforeTheFirstProvider')]
    public function testRefusesAPlaceBeforeTheFirst(int $position): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be at least 1');

        new SortOptionOverride('name', true, null, $position);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function placeBeforeTheFirstProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-3],
        ];
    }

    /**
     * The keys below are the published factory contract: renaming a constructor parameter must fail here.
     *
     * @return void
     */
    public function testIsBuiltFromTheNamedKeysTheFactoryReceives(): void
    {
        $data = [
            'code' => 'relevance',
            'shown' => false,
            'label' => 'Best match',
            'position' => 3,
        ];

        $override = new SortOptionOverride(...$data);

        self::assertSame('relevance', $override->getCode());
        self::assertFalse($override->isShown());
        self::assertSame('Best match', $override->getLabel());
        self::assertSame(3, $override->getPosition());
    }
}
