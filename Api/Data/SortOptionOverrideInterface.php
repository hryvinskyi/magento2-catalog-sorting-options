<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Api\Data;

/**
 * What a store changes for one storefront sort option: whether it is shown, its label and its place in the list
 *
 * It is valid by construction: the code is a non-blank, trimmed string; the label is trimmed and a blank one
 * means "keep the current label" (null); the place in list is null ("keep its place") or a whole number of at
 * least 1, where 1 is the first place.
 *
 * Create it through the generated `Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterfaceFactory`.
 * Its `create()` keys are a published contract, exactly:
 *
 * - `code` (string) — the sort option code, e.g. `position`, `name`, `price`, `relevance`
 * - `shown` (bool) — false removes the option from the list
 * - `label` (string|null) — the replacement label; blank or null keeps the current one
 * - `position` (int|null) — the place in list; null keeps the option's place
 *
 * The factory accepts any array, so a misspelt key passes static analysis and fails only at runtime.
 *
 * @api
 */
interface SortOptionOverrideInterface
{
    /**
     * Get the code of the sort option this override changes
     *
     * @return string Never blank, already trimmed
     */
    public function getCode(): string;

    /**
     * Whether the sort option stays in the list
     *
     * @return bool
     */
    public function isShown(): bool;

    /**
     * Get the label that replaces the option's current one
     *
     * @return string|null Null keeps the current label; never blank, already trimmed
     */
    public function getLabel(): ?string;

    /**
     * Get the place in list the option is moved to, 1 being the first place
     *
     * @return int|null Null keeps the option's place; otherwise at least 1
     */
    public function getPosition(): ?int;
}
