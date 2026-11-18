<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionSourceInterface;

/**
 * Offers the relevance option, which search result listings add to their sort options
 */
class SearchRelevanceSortOption implements SortOptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function getSortOptions(): array
    {
        return ['relevance' => (string)__('Relevance')];
    }
}
