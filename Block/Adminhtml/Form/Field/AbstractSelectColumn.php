<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

/**
 * Select rendered inside one cell of a dynamic-rows field
 *
 * The dynamic-rows field hands each column renderer its input name and id; a select keeps them under the names
 * the option hash is computed from, so the hash a row asks for matches the one printed in the cell template.
 */
abstract class AbstractSelectColumn extends Select
{
    /**
     * Receive the name of the cell input from the dynamic-rows field
     *
     * @param string $value Input name
     * @return $this
     */
    public function setInputName(string $value): static
    {
        $this->setData('name', $value);

        return $this;
    }

    /**
     * Receive the id of the cell input from the dynamic-rows field
     *
     * @param string $value Input id
     * @return $this
     */
    public function setInputId(string $value): static
    {
        $this->setData('id', $value);

        return $this;
    }
}
