<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Model\Source\ConfiguredSortOptions;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The source offers the options it was configured with, labels as strings whether they arrive as strings or as
 * translated phrases, and refuses an item without a usable code or label, naming it.
 */
class ConfiguredSortOptionsTest extends TestCase
{
    /**
     * @return void
     */
    public function testWithoutOptionsOffersNothing(): void
    {
        self::assertSame([], (new ConfiguredSortOptions())->getSortOptions());
    }

    /**
     * @return void
     */
    public function testOffersTheConfiguredOptionsAsStringsInTheirOrder(): void
    {
        $source = new ConfiguredSortOptions([
            'bestsellers' => new Phrase('Best Sellers'),
            'rating' => 'Rating',
        ]);

        self::assertSame(['bestsellers' => 'Best Sellers', 'rating' => 'Rating'], $source->getSortOptions());
    }

    /**
     * @param array<mixed> $options Configured options holding one bad item
     * @param string $named The item the failure names
     * @return void
     */
    #[DataProvider('badOptions')]
    public function testRefusesAnItemWithoutANonBlankCodeAndLabelNamingIt(array $options, string $named): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Sort option "%s"', $named));

        new ConfiguredSortOptions($options);
    }

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function badOptions(): array
    {
        return [
            'blank label' => [['rating' => '  '], 'rating'],
            'blank phrase label' => [['rating' => new Phrase(' ')], 'rating'],
            'array label' => [['rating' => ['Rating']], 'rating'],
            'null label' => [['rating' => null], 'rating'],
            'blank code' => [[' ' => 'Rating'], ' '],
        ];
    }
}
