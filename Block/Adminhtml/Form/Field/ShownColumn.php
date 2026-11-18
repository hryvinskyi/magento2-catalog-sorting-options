<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field;

/**
 * Yes/No select for the "Show" column of the sort option overrides field
 */
class ShownColumn extends AbstractSelectColumn
{
    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if ($this->getOptions() === []) {
            $this->setOptions([
                ['value' => '1', 'label' => (string)__('Yes')],
                ['value' => '0', 'label' => (string)__('No')],
            ]);
        }

        return parent::_toHtml();
    }
}
