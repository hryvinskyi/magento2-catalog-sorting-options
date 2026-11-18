<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

/**
 * One contributor to the sort options an admin can pick from
 *
 * A module that adds a sort option code to listings registers a source in the `sources` pool of the option
 * catalogue, so the admin can override that code too.
 *
 * @api
 */
interface SortOptionSourceInterface
{
    /**
     * Get the sort options this source contributes
     *
     * @return array<string, string> Option code => admin label
     */
    public function getSortOptions(): array;
}
