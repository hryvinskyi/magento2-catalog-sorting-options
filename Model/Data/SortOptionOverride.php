<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model\Data;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;

/**
 * Immutable sort option override, valid by construction
 *
 * The constructor's parameter names are the keys callers pass to the generated factory's `create()`; renaming
 * one breaks every caller at runtime, not at compile time.
 */
class SortOptionOverride implements SortOptionOverrideInterface
{
    /**
     * @var string
     */
    private readonly string $code;

    /**
     * @var string|null
     */
    private readonly ?string $label;

    /**
     * @param string $code Sort option code; surrounding whitespace is dropped, a blank code is refused
     * @param bool $shown False removes the option from the list
     * @param string|null $label Replacement label; trimmed, a blank one keeps the current label
     * @param int|null $position Place in list, 1 being the first; null keeps the option's place
     * @throws \InvalidArgumentException When the code is blank or the place in list is below 1
     */
    public function __construct(
        string $code,
        private readonly bool $shown,
        ?string $label = null,
        private readonly ?int $position = null
    ) {
        $code = trim($code);
        if ($code === '') {
            throw new \InvalidArgumentException('A sort option override needs an option code; the code is blank.');
        }
        if ($position !== null && $position < 1) {
            throw new \InvalidArgumentException(sprintf(
                'The place in list of sort option "%s" must be at least 1; %d given.',
                $code,
                $position
            ));
        }

        $label = $label === null ? '' : trim($label);
        $this->code = $code;
        $this->label = $label === '' ? null : $label;
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
    public function isShown(): bool
    {
        return $this->shown;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): ?int
    {
        return $this->position;
    }
}
