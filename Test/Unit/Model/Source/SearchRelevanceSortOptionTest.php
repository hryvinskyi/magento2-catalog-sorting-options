<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Model\Source\SearchRelevanceSortOption;
use PHPUnit\Framework\TestCase;

/**
 * The source offers the relevance option, and only that one, with a string label.
 */
class SearchRelevanceSortOptionTest extends TestCase
{
    /**
     * @return void
     */
    public function testOffersOnlyRelevance(): void
    {
        self::assertSame(['relevance' => 'Relevance'], (new SearchRelevanceSortOption())->getSortOptions());
    }
}
