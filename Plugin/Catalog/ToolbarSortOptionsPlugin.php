<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Applies the store's sort option policy to the options a product listing toolbar offers
 *
 * Every storefront path (category listing, quick and advanced search, the sort dropdown, the selected order and the
 * order taken from the URL) reads the toolbar's available orders, so filtering that one read covers them all.
 * The result depends only on the store and that store's settings, never on the customer, session or request.
 */
class ToolbarSortOptionsPlugin
{
    /**
     * @param SortOptionPolicyResolverInterface $resolver Gives the policy configured for a store
     * @param StoreManagerInterface $storeManager Gives the store the listing is served for
     */
    public function __construct(
        private readonly SortOptionPolicyResolverInterface $resolver,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Hide, relabel and reorder the available sort options for the current store
     *
     * An empty or non-array result is returned untouched, before the store is resolved.
     *
     * @param Toolbar $subject Toolbar block
     * @param mixed $result Available orders, option code => label
     * @return mixed
     */
    public function afterGetAvailableOrders(Toolbar $subject, mixed $result): mixed
    {
        if (!is_array($result) || $result === []) {
            return $result;
        }

        return $this->resolver->forStore((int) $this->storeManager->getStore()->getId())->apply($result);
    }
}
