<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionSourceInterface;

/**
 * Offers every registered listing sort option, so the admin can hide, rename and move it like any other option
 *
 * Every registered option is listed whether or not a store offers it: a setting may be saved at a scope before the
 * option is switched on there.
 */
class ListingSortOptionsSource implements SortOptionSourceInterface
{
    /**
     * @param ListingSortOptionsInterface $listingSortOptions Every registered listing sort option
     */
    public function __construct(
        private readonly ListingSortOptionsInterface $listingSortOptions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSortOptions(): array
    {
        return $this->listingSortOptions->all();
    }
}
