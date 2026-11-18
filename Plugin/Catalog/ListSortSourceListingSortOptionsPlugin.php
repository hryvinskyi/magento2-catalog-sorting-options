<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Plugin\Catalog;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Magento\Catalog\Model\Config\Source\ListSort;

/**
 * Lets an admin pick a listing sort option as the store's "Product Listing Sort by" default
 *
 * Admin only. Every registered option is listed, offered at the scope being edited or not: where the option is not
 * offered, listings fall back to their first option, as for any default they do not show.
 */
class ListSortSourceListingSortOptionsPlugin
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
     * @param ListSort $subject Default sort-by config source
     * @param mixed $result Options, each with `label` and `value`
     * @return mixed
     */
    public function afterToOptionArray(ListSort $subject, mixed $result): mixed
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
