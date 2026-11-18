<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Adds the listing sort options the current store offers to the sort options every storefront listing starts from
 *
 * Storefront only. The catalog's list of sortable options is where a category's own Sort By list is filtered (a
 * category that names a code shows it only when this list has it), where quick and advanced search take their
 * options, and where the toolbar falls back when nobody set its options. Adding a code here therefore reaches every
 * listing; hiding, renaming and moving options stays on the toolbar's available orders, which then apply to the
 * added codes like to any other.
 *
 * A code the list already holds keeps its entry, so a product attribute of the same code is never displaced.
 */
class CatalogConfigListingSortOptionsPlugin
{
    /**
     * @param ListingSortOptionsInterface $listingSortOptions Gives the options a store offers, once per request
     * @param StoreManagerInterface $storeManager Gives the store the listing is served for
     */
    public function __construct(
        private readonly ListingSortOptionsInterface $listingSortOptions,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Append the offered listing sort options whose codes the list does not hold yet
     *
     * @param CatalogConfig $subject Catalog config
     * @param mixed $result Sortable options, code => label
     * @return mixed
     */
    public function afterGetAttributeUsedForSortByArray(CatalogConfig $subject, mixed $result): mixed
    {
        if (!is_array($result)) {
            return $result;
        }

        $offered = $this->listingSortOptions->offeredIn((int)$this->storeManager->getStore()->getId());

        return $offered === [] ? $result : $result + $offered;
    }
}
