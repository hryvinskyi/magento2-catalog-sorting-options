<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;
use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Exception\InvalidSortOptionOverridesException;
use Hryvinskyi\CatalogSortingOptions\Model\Data\SortOptionOverride;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionOverridesParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The parser reads admin rows into overrides in row order, treats a shown flag as on unless it says no, keeps
 * a place only when it is a whole number of at least 1, and refuses a row it cannot make sense of.
 */
class SortOptionOverridesParserTest extends TestCase
{
    /**
     * @var SortOptionOverrideInterfaceFactory&MockObject
     */
    private $overrideFactory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->overrideFactory = $this->createMock(SortOptionOverrideInterfaceFactory::class);
        $this->overrideFactory->method('create')->willReturnCallback(self::buildOverride(...));
    }

    /**
     * @return void
     */
    public function testNoRowsGiveNoOverrides(): void
    {
        self::assertSame([], $this->parser()->parse([]));
    }

    /**
     * @return void
     */
    public function testRowsBecomeOverridesInRowOrderAndRowIdsAreDropped(): void
    {
        $overrides = $this->parser()->parse([
            '_17_1' => ['code' => 'price', 'shown' => '1', 'label' => 'Cost', 'position' => '2'],
            'abc' => ['code' => 'position', 'shown' => '0', 'label' => '', 'position' => ''],
        ]);

        self::assertCount(2, $overrides);
        self::assertSame(['price', true, 'Cost', 2], $this->describe($overrides[0]));
        self::assertSame(['position', false, null, null], $this->describe($overrides[1]));
    }

    /**
     * @return void
     */
    public function testRepeatedCodesAreNotRefusedHere(): void
    {
        $overrides = $this->parser()->parse([
            'a' => ['code' => 'price', 'shown' => '0'],
            'b' => ['code' => 'price', 'shown' => '1'],
        ]);

        self::assertCount(2, $overrides);
    }

    /**
     * @param mixed $shown Stored flag
     * @param bool $expected Whether the option stays shown
     * @return void
     */
    #[DataProvider('shownFlags')]
    public function testShownIsOnUnlessTheFlagSaysNo(mixed $shown, bool $expected): void
    {
        $overrides = $this->parser()->parse([['code' => 'price', 'shown' => $shown]]);

        self::assertSame($expected, $overrides[0]->isShown());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function shownFlags(): array
    {
        return [
            'string zero' => ['0', false],
            'integer zero' => [0, false],
            'false' => [false, false],
            'string one' => ['1', true],
            'integer one' => [1, true],
            'true' => [true, true],
            'empty string' => ['', true],
            'null' => [null, true],
        ];
    }

    /**
     * @return void
     */
    public function testAMissingShownFlagMeansShown(): void
    {
        $overrides = $this->parser()->parse([['code' => 'price']]);

        self::assertTrue($overrides[0]->isShown());
    }

    /**
     * @return void
     */
    public function testLabelAndPlaceAreOptionalAndAMissingOneMeansKeep(): void
    {
        $overrides = $this->parser()->parse([['code' => 'price', 'shown' => '1']]);

        self::assertNull($overrides[0]->getLabel());
        self::assertNull($overrides[0]->getPosition());
    }

    /**
     * @return void
     */
    public function testALabelThatIsNotAStringIsTreatedAsNoLabel(): void
    {
        $overrides = $this->parser()->parse([['code' => 'price', 'label' => ['x']]]);

        self::assertNull($overrides[0]->getLabel());
    }

    /**
     * @param mixed $value Stored place
     * @param int|null $expected Place the override gets
     * @return void
     */
    #[DataProvider('acceptedPlaces')]
    public function testAPlaceIsBlankOrAWholeNumberFromOne(mixed $value, ?int $expected): void
    {
        $overrides = $this->parser()->parse([['code' => 'price', 'position' => $value]]);

        self::assertSame($expected, $overrides[0]->getPosition());
    }

    /**
     * @return array<string, array{mixed, int|null}>
     */
    public static function acceptedPlaces(): array
    {
        return [
            'null' => [null, null],
            'empty string' => ['', null],
            'blank string' => ['   ', null],
            'digit string' => ['3', 3],
            'padded digit string' => [' 12 ', 12],
            'leading zeros' => ['007', 7],
            'integer' => [4, 4],
            'one' => ['1', 1],
        ];
    }

    /**
     * @param mixed $value Stored place
     * @return void
     */
    #[DataProvider('refusedPlaces')]
    public function testAnyOtherPlaceIsRefused(mixed $value): void
    {
        $this->expectException(InvalidSortOptionOverridesException::class);
        $this->expectExceptionMessage('"price"');

        $this->parser()->parse([['code' => 'price', 'position' => $value]]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function refusedPlaces(): array
    {
        return [
            'zero string' => ['0'],
            'zero integer' => [0],
            'negative string' => ['-1'],
            'negative integer' => [-2],
            'decimal string' => ['1.5'],
            'float' => [2.0],
            'words' => ['first'],
            'true' => [true],
            'false' => [false],
            'array' => [[1]],
        ];
    }

    /**
     * @param mixed $row Stored row
     * @return void
     */
    #[DataProvider('unusableRows')]
    public function testARowWithoutAUsableCodeIsRefused(mixed $row): void
    {
        $this->expectException(InvalidSortOptionOverridesException::class);

        $this->parser()->parse(['r1' => $row]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableRows(): array
    {
        return [
            'not an array' => ['price'],
            'null row' => [null],
            'no code' => [['shown' => '1']],
            'null code' => [['code' => null]],
            'empty code' => [['code' => '']],
            'blank code' => [['code' => "  \t"]],
            'integer code' => [['code' => 5]],
            'array code' => [['code' => ['price']]],
        ];
    }

    /**
     * @return void
     */
    public function testTheMessageNamesTheOffendingRow(): void
    {
        $this->expectException(InvalidSortOptionOverridesException::class);
        $this->expectExceptionMessage('"_5_9"');

        $this->parser()->parse(['ok' => ['code' => 'price'], '_5_9' => ['code' => '']]);
    }

    /**
     * Build a real override from the array the generated factory's create() receives
     *
     * @param array{code: string, shown: bool, label: string|null, position: int|null} $data Arguments keyed by
     *     constructor parameter name
     * @return SortOptionOverride
     */
    private static function buildOverride(array $data): SortOptionOverride
    {
        return new SortOptionOverride(...$data);
    }

    /**
     * Describe an override as a comparable list
     *
     * @param SortOptionOverrideInterface $override Override to describe
     * @return array{string, bool, string|null, int|null}
     */
    private function describe(SortOptionOverrideInterface $override): array
    {
        return [$override->getCode(), $override->isShown(), $override->getLabel(), $override->getPosition()];
    }

    /**
     * Build the parser under test
     *
     * @return SortOptionOverridesParser
     */
    private function parser(): SortOptionOverridesParser
    {
        return new SortOptionOverridesParser($this->overrideFactory);
    }
}
