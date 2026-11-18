<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;

/**
 * Decides the direction a listing opens in for its current order when the shopper chose no direction
 *
 * One place owns every rule that overrides the toolbar's own default direction, so two rules never compete for the
 * same listing. The rules, first match wins:
 *
 * - the order is a listing sort option: that option's default direction;
 * - the store's policy hides the listing's default order and the listing's shown options replace it: ascending,
 *   for every order of that listing, the replacement and any order the shopper picked alike;
 * - otherwise none, and the toolbar's own default direction stands.
 *
 * A direction the shopper chose is not this resolver's concern: it always wins over the answer.
 */
interface DefaultDirectionResolverInterface
{
    /**
     * Get the default direction of a listing's current order
     *
     * @param string $order The listing's current order code
     * @param SortOptionPolicyInterface $policy The sort option policy of the store being served
     * @param string|null $knownDefaultOrder The listing's default order when known; a caller may pass null when the
     *     policy does not hide it, since only a hidden default can be replaced
     * @param array<array-key,mixed> $availableOrders The options the listing shows, code => label; only read when
     *     the default order is hidden
     * @return SortDirection|null Null when no rule applies
     */
    public function forOrder(
        string $order,
        SortOptionPolicyInterface $policy,
        ?string $knownDefaultOrder,
        array $availableOrders
    ): ?SortDirection;
}
