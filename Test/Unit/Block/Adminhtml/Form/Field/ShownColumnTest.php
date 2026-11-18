<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Block\Adminhtml\Form\Field;

use Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field\ShownColumn;

/**
 * The show column is a Yes/No select whose values are the ones the storefront reads back: 1 shows, 0 hides.
 */
class ShownColumnTest extends RendererTestCase
{
    /**
     * @return void
     */
    public function testOffersYesAsOneAndNoAsZeroUnderTheNameAndIdTheFieldGives(): void
    {
        $column = new ShownColumn($this->rendererContext());
        $column->setInputName('overrides[row][shown]')->setInputId('row_shown');

        $html = $column->toHtml();

        self::assertStringContainsString('name="overrides[row][shown]" id="row_shown"', $html);
        self::assertStringContainsString('<option value="1" >Yes</option>', $html);
        self::assertStringContainsString('<option value="0" >No</option>', $html);
    }
}
