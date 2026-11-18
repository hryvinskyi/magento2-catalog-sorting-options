<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Magento\Catalog\Model\Category\Attribute\Source\Sortby;

/**
 * Lets an admin pick a listing sort option in a category's "Available Product Listing Sort By" and
 * "Default Product Listing Sort By"
 *
 * Admin only. Every registered option is listed, offered in a store or not: a category is shared by stores, and the
 * storefront shows a category's code only where its store offers it.
 */
class CategorySortBySourceListingSortOptionsPlugin
{
    /**
     * @param ListingSortOptionsInterface $listingSortOptions Every registered listing sort option
     */
    public function __construct(
        private readonly ListingSortOptionsInterface $listingSortOptions
    ) {
    }

    /**
     * Append every listing sort option whose code the list does not hold yet, as a label and a value
     *
     * @param Sortby $subject Category sort-by source
     * @param mixed $result Options, each with `label` and `value`
     * @return mixed
     */
    public function afterGetAllOptions(Sortby $subject, mixed $result): mixed
    {
        if (!is_array($result)) {
            return $result;
        }

        $present = [];
        foreach ($result as $option) {
            if (is_array($option) && is_scalar($option['value'] ?? null)) {
                $present[(string)$option['value']] = true;
            }
        }

        foreach ($this->listingSortOptions->all() as $code => $label) {
            if (!isset($present[$code])) {
                $result[] = ['label' => $label, 'value' => $code];
            }
        }

        return $result;
    }
}
