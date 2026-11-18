<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Exception;

/**
 * A store's overrides name one sort option code more than once
 */
class DuplicateSortOptionOverrideException extends \InvalidArgumentException
{
    /**
     * @param string $sortOptionCode The code that repeats
     * @param \Throwable|null $previous Previous exception, if any
     */
    public function __construct(
        private readonly string $sortOptionCode,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('Sort option "%s" has more than one override.', $sortOptionCode),
            0,
            $previous
        );
    }

    /**
     * Get the code that repeats
     *
     * @return string
     */
    public function getSortOptionCode(): string
    {
        return $this->sortOptionCode;
    }
}
