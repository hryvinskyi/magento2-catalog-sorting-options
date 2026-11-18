<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api;

/**
 * The sort option overrides of one store, applied to the options a product listing offers
 *
 * Applying the policy hides, relabels and moves options:
 *
 * - a hidden code is removed; every other option keeps its relative order unless a place in list moves it;
 * - a non-blank label replaces the option's label;
 * - options with a place in list are put at that place (1 = first), the others fill the remaining places in
 *   their current relative order; a place beyond the end of the list puts the option at the end, and options
 *   sharing one place keep their current order;
 * - a listing is never left without options: when hiding would remove all of them, every option stays, still
 *   relabelled and moved;
 * - overrides for codes the listing does not offer are ignored;
 * - applying the policy to its own output changes nothing, so code that reads the list, edits it and sets it
 *   back can have it applied twice.
 *
 * A policy never holds two overrides for one code.
 *
 * Create it through the generated `Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterfaceFactory` with
 * the key `overrides` (a list of {@see Data\SortOptionOverrideInterface}). It throws
 * {@see \Hryvinskyi\CatalogSortingOptions\Exception\DuplicateSortOptionOverrideException} when a code repeats.
 *
 * @api
 */
interface SortOptionPolicyInterface
{
    /**
     * Apply the overrides to the options a listing offers
     *
     * Keys are option codes, matched as strings, so a numeric key still matches its override. Values are labels
     * and are not interpreted: an untouched label keeps its value and type, a replaced label is a string.
     *
     * @param array<array-key,mixed> $options Option code => label, in the listing's order
     * @return array<array-key, mixed> The shown options, code => label, in their new order
     */
    public function apply(array $options): array;

    /**
     * Get the code that replaces a listing's default order when this policy hides it
     *
     * @param string $defaultCode The listing's configured default order
     * @param array<array-key,mixed> $shownOptions The listing's options after {@see apply()}
     * @return string|null The first shown option's code; null when the policy does not hide the default, when
     *     the default is still among the shown options (a listing is never left without options), or when no
     *     option is shown
     */
    public function replacementForDefault(string $defaultCode, array $shownOptions): ?string;

    /**
     * Whether any override hides its option
     *
     * @return bool
     */
    public function hidesAnything(): bool;

    /**
     * Whether an override hides this code
     *
     * It does not tell whether a listing still shows the option because hiding would have emptied it.
     *
     * @param string $code Sort option code
     * @return bool
     */
    public function hides(string $code): bool;
}
