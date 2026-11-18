<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionInterface;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;

/**
 * The listing sort options registered in the `options` pool, by code
 *
 * Contributions go into the pool from a module's global `di.xml`: an area `di.xml` naming the same argument would
 * replace the whole list in that area. A pool item that is not a listing sort option, or a second option for a code
 * already registered, fails the construction and names the item or code, so a misregistered option never silently
 * disappears or shadows another.
 *
 * It sits on the storefront path of every listing render, so it reads nothing but config: the options a store
 * offers are collected once per store and request.
 */
class ListingSortOptions implements ListingSortOptionsInterface
{
    /**
     * @var array<string, ListingSortOptionInterface> Options by code, in pool order
     */
    private readonly array $options;

    /**
     * @var array<int, array<string, string>> Offered options already collected, by store id
     */
    private array $offered = [];

    /**
     * @param array<mixed> $options Listing sort options, already ordered by their `sortOrder`
     * @throws \InvalidArgumentException When an item is not a listing sort option or a code is registered twice
     */
    public function __construct(array $options = [])
    {
        $this->options = $this->byCode($options);
    }

    /**
     * @inheritDoc
     */
    public function offeredIn(int $storeId): array
    {
        return $this->offered[$storeId] ??= $this->collectOffered($storeId);
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $labels = [];
        foreach ($this->options as $code => $option) {
            $labels[$code] = $option->getLabel();
        }

        return $labels;
    }

    /**
     * @inheritDoc
     */
    public function defaultDirectionOf(string $code): ?SortDirection
    {
        return isset($this->options[$code]) ? $this->options[$code]->getDefaultDirection() : null;
    }

    /**
     * Collect the options a store offers, code => label
     *
     * @param int $storeId Store view id
     * @return array<string, string>
     */
    private function collectOffered(int $storeId): array
    {
        $offered = [];
        foreach ($this->options as $code => $option) {
            if ($option->isOffered($storeId)) {
                $offered[$code] = $option->getLabel();
            }
        }

        return $offered;
    }

    /**
     * Index the pool items by their code
     *
     * @param array<mixed> $options Pool items
     * @return array<string, ListingSortOptionInterface>
     * @throws \InvalidArgumentException When an item is not a listing sort option or a code is registered twice
     */
    private function byCode(array $options): array
    {
        $byCode = [];
        foreach ($options as $key => $option) {
            if (!$option instanceof ListingSortOptionInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Listing sort option "%s" must implement %s.',
                    $key,
                    ListingSortOptionInterface::class
                ));
            }

            $code = $option->getCode();
            if (isset($byCode[$code])) {
                throw new \InvalidArgumentException(sprintf(
                    'Listing sort option code "%s" is registered more than once.',
                    $code
                ));
            }
            $byCode[$code] = $option;
        }

        return $byCode;
    }
}
