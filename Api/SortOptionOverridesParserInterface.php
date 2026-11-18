<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;
use Hryvinskyi\CatalogSortingOptions\Exception\InvalidSortOptionOverridesException;

/**
 * Turns the stored rows of the sort options setting into overrides
 *
 * Each row is expected as `['code' => string, 'shown' => '0'|'1', 'label' => string, 'position' => string]`.
 * Row keys (the admin row ids) are discarded; the overrides come back in row order. Repeated codes are not
 * checked here: the policy refuses them.
 *
 * @api
 */
interface SortOptionOverridesParserInterface
{
    /**
     * Parse the stored rows into overrides
     *
     * @param array<array-key,mixed> $rows Row id => row
     * @return list<SortOptionOverrideInterface>
     * @throws InvalidSortOptionOverridesException When a row is not an array, has no code or a blank one, or has
     *     a place in list that is neither blank nor a whole number of at least 1
     */
    public function parse(array $rows): array;
}
