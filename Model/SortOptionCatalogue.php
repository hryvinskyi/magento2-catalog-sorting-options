<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionCatalogueInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionSourceInterface;

/**
 * Merges the options of every registered source into the catalogue the admin picks sort options from
 *
 * Sources are read in pool order. When two sources offer one code, the first label wins. A pool item that is not
 * a source fails the construction, naming the item, so a misregistered source never silently disappears.
 */
class SortOptionCatalogue implements SortOptionCatalogueInterface
{
    /**
     * @var list<SortOptionSourceInterface>
     */
    private readonly array $sources;

    /**
     * @param array<mixed> $sources Sort option sources, already ordered by their `sortOrder`
     * @throws \InvalidArgumentException When an item is not a sort option source
     */
    public function __construct(array $sources = [])
    {
        $this->sources = $this->validSources($sources);
    }

    /**
     * @inheritDoc
     */
    public function getSortOptions(): array
    {
        $options = [];
        foreach ($this->sources as $source) {
            $options += $source->getSortOptions();
        }

        return $options;
    }

    /**
     * Keep the sources as a list in their given order
     *
     * @param array<mixed> $sources Sources from the pool
     * @return list<SortOptionSourceInterface>
     * @throws \InvalidArgumentException When an item is not a sort option source
     */
    private function validSources(array $sources): array
    {
        $valid = [];
        foreach ($sources as $key => $source) {
            if (!$source instanceof SortOptionSourceInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Sort option source "%s" must implement %s.',
                    $key,
                    SortOptionSourceInterface::class
                ));
            }
            $valid[] = $source;
        }

        return $valid;
    }
}
