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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * A listing sort option offered in the stores where a Yes/No setting is on
 *
 * A module registers one as a `virtualType` with the arguments `code`, `label` (marked `translate="true"` so the
 * phrase is collected), `enabledPath` and, when its listings should open ascending, `defaultDirection` = `asc`.
 * The container hands the label over untranslated, so it is translated each time it is read, in the locale of that
 * moment. Every argument is checked at construction: a blank code, label or setting path, or a direction other
 * than `asc` or `desc`, fails loudly instead of yielding an option no listing can use.
 */
class ConfigFlagListingSortOption implements ListingSortOptionInterface
{
    /**
     * @var string
     */
    private readonly string $code;

    /**
     * @var string
     */
    private readonly string $label;

    /**
     * @var string
     */
    private readonly string $enabledPath;

    /**
     * @var SortDirection
     */
    private readonly SortDirection $defaultDirection;

    /**
     * @param ScopeConfigInterface $scopeConfig Reads the Yes/No setting per store
     * @param string $code Sort code, the value a listing URL carries in `product_list_order`
     * @param string $label Label source text, translated when read
     * @param string $enabledPath Config path of the Yes/No setting that offers the option, read at store scope
     * @param string $defaultDirection Direction a listing sorted by the option opens in: `asc` or `desc`
     * @throws \InvalidArgumentException When the code, label or path is blank, or the direction is unknown
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        string $code,
        string $label,
        string $enabledPath,
        string $defaultDirection = 'desc'
    ) {
        $this->code = $this->nonBlank($code, 'code', $code);
        $this->label = $this->nonBlank($label, 'label', $code);
        $this->enabledPath = $this->nonBlank($enabledPath, 'enabledPath', $code);
        $this->defaultDirection = $this->direction($defaultDirection, $code);
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string)__($this->label);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultDirection(): SortDirection
    {
        return $this->defaultDirection;
    }

    /**
     * @inheritDoc
     */
    public function isOffered(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($this->enabledPath, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Keep a required argument that must hold a non-space character
     *
     * @param string $value Argument value
     * @param string $argument Argument name, for the message
     * @param string $code Sort code, for the message
     * @return string The value unchanged
     * @throws \InvalidArgumentException When the value is blank
     */
    private function nonBlank(string $value, string $argument, string $code): string
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException(sprintf(
                'Listing sort option "%s" needs a non-blank %s.',
                $code,
                $argument
            ));
        }

        return $value;
    }

    /**
     * Turn the configured default direction into a sort direction
     *
     * @param string $direction `asc` or `desc`, in any case
     * @param string $code Sort code, for the message
     * @return SortDirection
     * @throws \InvalidArgumentException When the direction is neither
     */
    private function direction(string $direction, string $code): SortDirection
    {
        $known = SortDirection::tryFrom(strtolower(trim($direction)));
        if ($known === null) {
            throw new \InvalidArgumentException(sprintf(
                'Listing sort option "%s" has default direction "%s"; use "asc" or "desc".',
                $code,
                $direction
            ));
        }

        return $known;
    }
}
