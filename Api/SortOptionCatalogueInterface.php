<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

/**
 * Every sort option an admin can pick from when overriding one
 *
 * Admin only: the storefront policy never consults it, so a code missing here can still be overridden.
 *
 * @api
 */
interface SortOptionCatalogueInterface
{
    /**
     * Get every contributed sort option, in source order; for a code offered twice the first label wins
     *
     * @return array<string, string> Option code => admin label
     */
    public function getSortOptions(): array;
}
