<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

/**
 * Gives the sort option policy configured for a store
 *
 * It never fails because of what is stored: an unreadable stored value yields a policy without overrides.
 *
 * @api
 */
interface SortOptionPolicyResolverInterface
{
    /**
     * Config path of the sort option overrides, read at store scope
     */
    public const XML_PATH_OVERRIDES = 'catalog_sorting/options/overrides';

    /**
     * Get the policy of a store
     *
     * @param int $storeId Store view id
     * @return SortOptionPolicyInterface
     */
    public function forStore(int $storeId): SortOptionPolicyInterface;
}
