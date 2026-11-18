<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;
use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionOverridesParserInterface;
use Hryvinskyi\CatalogSortingOptions\Exception\InvalidSortOptionOverridesException;

/**
 * Reads the rows of the sort options setting into overrides
 *
 * A row needs a non-blank option code. The option is shown unless the stored flag is `'0'`, `0` or `false`. The
 * label is taken when it is a string. The place in list is blank (no place) or a whole number of at least 1,
 * given as an integer or as digits. Anything else in the place is refused rather than guessed at.
 */
class SortOptionOverridesParser implements SortOptionOverridesParserInterface
{
    /**
     * @param SortOptionOverrideInterfaceFactory $overrideFactory Creates one override per row
     */
    public function __construct(private readonly SortOptionOverrideInterfaceFactory $overrideFactory)
    {
    }

    /**
     * @inheritDoc
     */
    public function parse(array $rows): array
    {
        $overrides = [];
        foreach ($rows as $rowId => $row) {
            $overrides[] = $this->parseRow((string) $rowId, $row);
        }

        return $overrides;
    }

    /**
     * Turn one row into an override
     *
     * @param string $rowId Admin row id, used only to say which row is wrong
     * @param mixed $row The stored row
     * @return SortOptionOverrideInterface
     * @throws InvalidSortOptionOverridesException When the row is not an array, has no usable code or has a bad place
     */
    private function parseRow(string $rowId, mixed $row): SortOptionOverrideInterface
    {
        if (!is_array($row)) {
            throw new InvalidSortOptionOverridesException(
                sprintf('Sort option row "%s" is not a row.', $rowId)
            );
        }

        $code = $row['code'] ?? null;
        if (!is_string($code) || trim($code) === '') {
            throw new InvalidSortOptionOverridesException(
                sprintf('Sort option row "%s" has no option code.', $rowId)
            );
        }

        $label = $row['label'] ?? null;

        return $this->overrideFactory->create([
            'code' => $code,
            'shown' => !in_array($row['shown'] ?? true, ['0', 0, false], true),
            'label' => is_string($label) ? $label : null,
            'position' => $this->parsePosition($rowId, $code, $row['position'] ?? null),
        ]);
    }

    /**
     * Read the place in list of a row
     *
     * @param string $rowId Admin row id
     * @param string $code Option code of the row
     * @param mixed $value Stored place: absent, null, blank, an integer or digits
     * @return int|null Null when the row has no place
     * @throws InvalidSortOptionOverridesException When the value is not blank and not a whole number of at least 1
     */
    private function parsePosition(string $rowId, string $code, mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            $value = ctype_digit($value) ? (int) $value : $value;
        }
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 1) {
            throw new InvalidSortOptionOverridesException(sprintf(
                'Sort option row "%s" (option "%s"): the place in list must be blank or a whole number '
                . 'of at least 1.',
                $rowId,
                $code
            ));
        }

        return $value;
    }
}
