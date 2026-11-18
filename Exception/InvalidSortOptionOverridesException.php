<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Exception;

/**
 * The stored sort option settings cannot be read as overrides
 *
 * A row is not a row, has no option code, or has a place in list that is neither blank nor a whole number of at
 * least 1.
 */
class InvalidSortOptionOverridesException extends \InvalidArgumentException
{
}
