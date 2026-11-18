<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Model\Source\CatalogSortOptions;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

/**
 * The source offers what the catalog config lists for category sorting, with every label as a plain string.
 */
class CatalogSortOptionsTest extends TestCase
{
    /**
     * @return void
     */
    public function testOffersTheCatalogSortOptionsWithStringLabelsAndTheCodeForAMissingLabel(): void
    {
        $config = $this->createMock(CatalogConfig::class);
        $config->method('getAttributeUsedForSortByArray')->willReturn([
            'position' => new Phrase('Position'),
            'name' => 'Product Name',
            'price' => new Phrase('Price'),
            'rating' => null,
        ]);

        self::assertSame(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price', 'rating' => 'rating'],
            (new CatalogSortOptions($config))->getSortOptions()
        );
    }

    /**
     * @return void
     */
    public function testOffersNothingWhenTheCatalogListsNothing(): void
    {
        $config = $this->createMock(CatalogConfig::class);
        $config->method('getAttributeUsedForSortByArray')->willReturn([]);

        self::assertSame([], (new CatalogSortOptions($config))->getSortOptions());
    }
}
