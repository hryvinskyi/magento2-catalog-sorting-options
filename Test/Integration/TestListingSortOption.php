<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Integration;

/**
 * The identifiers of the listing sort option integration tests register, usable in test attributes
 */
class TestListingSortOption
{
    /**
     * Sort code
     */
    public const CODE = 'test_listing_option';

    /**
     * Label
     */
    public const LABEL = 'Test Listing Option';

    /**
     * Setting that offers the option, read at store scope
     */
    public const ENABLED_PATH = 'catalog_sorting/test_listing_option/enabled';
}
