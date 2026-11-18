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
 * A sort code a shopper can pick in a listing's Sort By list without being a product attribute
 *
 * A module that sorts listings by a value of its own registers one in the `options` pool of
 * {@see \Hryvinskyi\CatalogSortingOptions\Model\ListingSortOptions}. The option is then added to the storefront
 * listings of every store that offers it, to the admin lists a category's and the store's default sort are chosen
 * from, and to the sort options an admin can hide, rename and move. When the shopper chose no direction, a listing
 * sorted by it opens in the option's default direction.
 *
 * @api
 */
interface ListingSortOptionInterface
{
    /**
     * Get the sort code, the value a listing URL carries in `product_list_order`
     *
     * @return string Never blank
     */
    public function getCode(): string;

    /**
     * Get the label shown in Sort By, translated for the current locale
     *
     * @return string Never blank
     */
    public function getLabel(): string;

    /**
     * Get the direction a listing sorted by this option opens in when the shopper chose none
     *
     * @return SortDirection
     */
    public function getDefaultDirection(): SortDirection;

    /**
     * Whether the store offers this option on its listings
     *
     * @param int $storeId Store view id
     * @return bool
     */
    public function isOffered(int $storeId): bool;
}
