<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Exception\DuplicateSortOptionOverrideException;

/**
 * The sort option overrides of one store
 *
 * It runs on every uncached listing render, several times per toolbar, so whatever does not depend on the
 * listing is worked out once in the constructor: the overrides keyed by code, whether any of them hides its
 * option, and whether any of them has a place in list. A policy without overrides hands the options back as
 * they came, without looking at them.
 *
 * Moving options: on the options left after hiding, the ones with a place are ordered by place, then by their
 * current order; the others keep their current order. Each place in turn, from the first, takes the next option
 * with a place when that place is not beyond the current one, else the next option without one, else the next
 * option with a place. The result depends only on those two orders and the places, so a second application
 * returns the same list.
 */
class SortOptionPolicy implements SortOptionPolicyInterface
{
    /**
     * @var array<array-key, SortOptionOverrideInterface> Overrides keyed by option code
     */
    private readonly array $overrides;

    /**
     * @var bool
     */
    private readonly bool $hidesAnything;

    /**
     * @var bool
     */
    private readonly bool $hasPlaces;

    /**
     * @param array<mixed> $overrides The store's overrides, a list of SortOptionOverrideInterface
     * @throws DuplicateSortOptionOverrideException When two overrides name one code
     * @throws \InvalidArgumentException When an item is not a sort option override
     */
    public function __construct(array $overrides = [])
    {
        $byCode = [];
        $hidesAnything = false;
        $hasPlaces = false;
        foreach ($overrides as $key => $override) {
            if (!$override instanceof SortOptionOverrideInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Sort option override "%s" must implement %s.',
                    $key,
                    SortOptionOverrideInterface::class
                ));
            }
            $code = $override->getCode();
            if (isset($byCode[$code])) {
                throw new DuplicateSortOptionOverrideException($code);
            }
            $byCode[$code] = $override;
            $hidesAnything = $hidesAnything || !$override->isShown();
            $hasPlaces = $hasPlaces || $override->getPosition() !== null;
        }

        $this->overrides = $byCode;
        $this->hidesAnything = $hidesAnything;
        $this->hasPlaces = $hasPlaces;
    }

    /**
     * @inheritDoc
     */
    public function apply(array $options): array
    {
        if ($this->overrides === []) {
            return $options;
        }

        $relabelled = [];
        $shown = [];
        foreach ($options as $code => $label) {
            $override = $this->overrides[(string)$code] ?? null;
            $label = $override?->getLabel() ?? $label;
            $relabelled[$code] = $label;
            if ($override === null || $override->isShown()) {
                $shown[$code] = $label;
            }
        }

        if ($shown === []) {
            $shown = $relabelled;
        }

        return $this->hasPlaces ? $this->moved($shown) : $shown;
    }

    /**
     * @inheritDoc
     */
    public function replacementForDefault(string $defaultCode, array $shownOptions): ?string
    {
        if (!$this->hides($defaultCode) || array_key_exists($defaultCode, $shownOptions)) {
            return null;
        }

        $first = array_key_first($shownOptions);

        return $first === null ? null : (string)$first;
    }

    /**
     * @inheritDoc
     */
    public function hidesAnything(): bool
    {
        return $this->hidesAnything;
    }

    /**
     * @inheritDoc
     */
    public function hides(string $code): bool
    {
        $override = $this->overrides[$code] ?? null;

        return $override !== null && !$override->isShown();
    }

    /**
     * Put each option with a place in list at that place; the others fill the remaining places in their order
     *
     * @param array<array-key,mixed> $options Shown options, code => label, in their current order
     * @return array<array-key, mixed> The same options, keys and labels untouched, in their new order
     */
    private function moved(array $options): array
    {
        $placed = [];
        $unplaced = [];
        $index = 0;
        foreach ($options as $code => $label) {
            $position = ($this->overrides[(string)$code] ?? null)?->getPosition();
            if ($position === null) {
                $unplaced[] = $code;
            } else {
                $placed[] = ['code' => $code, 'position' => $position, 'index' => $index];
            }
            $index++;
        }

        if ($placed === []) {
            return $options;
        }

        usort(
            $placed,
            static fn (array $left, array $right): int =>
                [$left['position'], $left['index']] <=> [$right['position'], $right['index']]
        );

        $moved = [];
        $nextPlaced = 0;
        $nextUnplaced = 0;
        $placedCount = count($placed);
        $unplacedCount = count($unplaced);
        for ($place = 1; $place <= $index; $place++) {
            $takePlaced = $nextPlaced < $placedCount
                && ($placed[$nextPlaced]['position'] <= $place || $nextUnplaced >= $unplacedCount);
            $code = $takePlaced ? $placed[$nextPlaced++]['code'] : $unplaced[$nextUnplaced++];
            $moved[$code] = $options[$code];
        }

        return $moved;
    }
}
