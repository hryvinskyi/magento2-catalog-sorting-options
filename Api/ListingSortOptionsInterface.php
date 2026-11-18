<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;

/**
 * Every registered listing sort option, by code
 *
 * Codes are unique: two options never share one. Lists keep the registration order.
 *
 * @api
 */
interface ListingSortOptionsInterface
{
    /**
     * Get the options a store offers on its listings
     *
     * The answer is remembered per store for the rest of the request.
     *
     * @param int $storeId Store view id
     * @return array<string, string> Code => label, translated for the current locale
     */
    public function offeredIn(int $storeId): array;

    /**
     * Get every registered option, offered anywhere or not
     *
     * @return array<string, string> Code => label, translated for the current locale
     */
    public function all(): array;

    /**
     * Get the direction a listing sorted by a code opens in when the shopper chose none
     *
     * @param string $code Sort code
     * @return SortDirection|null Null when the code is not a registered listing sort option
     */
    public function defaultDirectionOf(string $code): ?SortDirection;
}
