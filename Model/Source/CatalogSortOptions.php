<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionSourceInterface;
use Magento\Catalog\Model\Config as CatalogConfig;

/**
 * Offers the sort options of category listings: the position and every attribute used for sorting
 *
 * Admin only. It depends on the catalog config class, which has no interface, because it is never part of the
 * storefront plugin's dependencies.
 */
class CatalogSortOptions implements SortOptionSourceInterface
{
    /**
     * @param CatalogConfig $catalogConfig Lists the sort options a category listing can offer
     */
    public function __construct(private readonly CatalogConfig $catalogConfig)
    {
    }

    /**
     * @inheritDoc
     */
    public function getSortOptions(): array
    {
        $options = [];
        foreach ($this->catalogConfig->getAttributeUsedForSortByArray() as $code => $label) {
            $options[(string)$code] = $this->asString($label, (string)$code);
        }

        return $options;
    }

    /**
     * Turn a label of the catalog config, a string or a translated phrase, into a string
     *
     * @param mixed $label Label as the catalog config returns it
     * @param string $fallback Used when the config gave no printable label: the option code
     * @return string
     */
    private function asString(mixed $label, string $fallback): string
    {
        if (is_string($label) || $label instanceof \Stringable) {
            return (string)$label;
        }

        return $fallback;
    }
}
