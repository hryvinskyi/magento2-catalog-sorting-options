<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Block\Adminhtml\Form\Field;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionCatalogueInterface;
use Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field\SortOptionCodeColumn;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * The option column offers every option of the catalogue, plus each code already saved in the field that the
 * catalogue no longer lists, so a saved row never silently becomes another code.
 */
class SortOptionCodeColumnTest extends RendererTestCase
{
    /**
     * @var SortOptionCatalogueInterface&MockObject
     */
    private $catalogue;

    /**
     * @var SortOptionCodeColumn
     */
    private SortOptionCodeColumn $column;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue = $this->createMock(SortOptionCatalogueInterface::class);
        $this->column = new SortOptionCodeColumn($this->rendererContext(), $this->catalogue);
        $this->column->setInputName('overrides[row][code]')->setInputId('row_code');
    }

    /**
     * @return void
     */
    public function testOffersTheCatalogueOptionsWithTheirAdminLabels(): void
    {
        $this->catalogue->method('getSortOptions')->willReturn(['position' => 'Position', 'name' => 'Product Name']);

        $html = $this->column->toHtml();

        self::assertStringContainsString('name="overrides[row][code]" id="row_code"', $html);
        self::assertSame(['position', 'name'], $this->offeredCodes($html));
        self::assertStringContainsString('>Position</option>', $html);
        self::assertStringContainsString('>Product Name</option>', $html);
    }

    /**
     * @return void
     */
    public function testAStoredCodeTheCatalogueDoesNotListIsStillOfferedAndSelectable(): void
    {
        $this->catalogue->method('getSortOptions')->willReturn(['position' => 'Position']);
        $this->column->setStoredCodes(['position', 'bestsellers']);

        $html = $this->column->setData('value', 'bestsellers')->toHtml();

        self::assertSame(['position', 'bestsellers'], $this->offeredCodes($html));
        self::assertStringContainsString(
            '<option value="bestsellers" selected="selected" >bestsellers (not offered by any listing now)</option>',
            $html
        );
    }

    /**
     * @return void
     */
    public function testAStoredCodeIsOfferedOnceWhenTheCatalogueAlreadyListsIt(): void
    {
        $this->catalogue->method('getSortOptions')->willReturn(['position' => 'Position', 'name' => 'Name']);
        $this->column->setStoredCodes(['name', 'name', 'old', 'old']);

        self::assertSame(['position', 'name', 'old'], $this->offeredCodes($this->column->toHtml()));
    }

    /**
     * @return void
     */
    public function testStoredCodesGivenAfterTheFirstRenderAreStillOffered(): void
    {
        $this->catalogue->method('getSortOptions')->willReturn(['position' => 'Position']);
        $this->column->toHtml();

        $this->column->setStoredCodes(['old']);

        self::assertSame(['position', 'old'], $this->offeredCodes($this->column->toHtml()));
    }

    /**
     * @return void
     */
    public function testANumericCatalogueCodeIsOfferedAsTheSameText(): void
    {
        $this->catalogue->method('getSortOptions')->willReturn([123 => 'Numbered']);
        $this->column->setStoredCodes(['123']);

        self::assertSame(['123'], $this->offeredCodes($this->column->toHtml()));
    }

    /**
     * List the option values of rendered select HTML
     *
     * @param string $html Rendered select
     * @return list<string>
     */
    private function offeredCodes(string $html): array
    {
        preg_match_all('/<option value="([^"]*)"/', $html, $matches);

        return $matches[1];
    }
}
