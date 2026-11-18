<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Api\DefaultDirectionResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;

/**
 * Applies the default direction rules in their order of precedence
 *
 * A listing sort option's own direction comes first, so a popularity order opens best-first even on a listing whose
 * default order is hidden. On a listing whose hidden default order is replaced, every other order opens ascending:
 * the option standing in for the default (a name or a price) would otherwise inherit a direction meant for another
 * order, such as a search listing's best-first relevance, and run Z to A; the listing's other orders follow the same
 * rule, so the listing has one default direction whichever order is current.
 */
class DefaultDirectionResolver implements DefaultDirectionResolverInterface
{
    /**
     * @param ListingSortOptionsInterface $listingSortOptions Gives the default direction of a listing sort option
     */
    public function __construct(
        private readonly ListingSortOptionsInterface $listingSortOptions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forOrder(
        string $order,
        SortOptionPolicyInterface $policy,
        ?string $knownDefaultOrder,
        array $availableOrders
    ): ?SortDirection {
        $listingOptionDirection = $this->listingSortOptions->defaultDirectionOf($order);
        if ($listingOptionDirection !== null) {
            return $listingOptionDirection;
        }

        if ($knownDefaultOrder === null || !$policy->hides($knownDefaultOrder)) {
            return null;
        }

        return $policy->replacementForDefault($knownDefaultOrder, $availableOrders) !== null
            ? SortDirection::Ascending
            : null;
    }
}
