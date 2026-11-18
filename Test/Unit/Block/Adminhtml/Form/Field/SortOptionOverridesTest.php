<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Block\Adminhtml\Form\Field;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionCatalogueInterface;
use Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field\ShownColumn;
use Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field\SortOptionCodeColumn;
use Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field\SortOptionOverrides;
use Magento\Backend\Block\Template\Context as BackendContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Text;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Each saved row marks both of its choices as selected through one set of option attributes, keyed by the hashes
 * the renderers print in the cell templates, and a saved code the catalogue no longer lists stays selectable.
 */
class SortOptionOverridesTest extends RendererTestCase
{
    private const ELEMENT_NAME = 'groups[options][fields][overrides][value]';

    /**
     * @var LayoutInterface&MockObject
     */
    private $layout;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $catalogue = $this->createMock(SortOptionCatalogueInterface::class);
        $catalogue->method('getSortOptions')->willReturn(['position' => 'Position', 'price' => 'Price']);
        $rendererContext = $this->rendererContext();

        $this->layout = $this->createMock(LayoutInterface::class);
        $this->layout->method('createBlock')->willReturnCallback(
            static function (
                string $type,
                string $name,
                array $arguments
            ) use (
                $rendererContext,
                $catalogue
            ): BlockInterface {
                /** @var array<string,mixed> $data */
                $data = $arguments['data'];

                return $type === ShownColumn::class
                    ? new ShownColumn($rendererContext, $data)
                    : new SortOptionCodeColumn($rendererContext, $catalogue, $data);
            }
        );
    }

    /**
     * @return void
     */
    public function testDeclaresTheFourColumnsAndTheAddButton(): void
    {
        $field = $this->field($this->element([]));

        self::assertSame(['code', 'shown', 'label', 'position'], array_keys($field->getColumns()));
        self::assertSame('Add Sort Option', (string)$field->getAddButtonLabel());
        self::assertFalse($field->isAddAfter());
        self::assertSame('Place in List', $this->columnLabel($field, 'position'));
        self::assertSame('Sort Option', $this->columnLabel($field, 'code'));
        self::assertSame('Show', $this->columnLabel($field, 'shown'));
        self::assertSame('Label', $this->columnLabel($field, 'label'));
        self::assertSame(
            'validate-digits validate-greater-than-zero',
            $this->column($field, 'position')['class'] ?? null
        );
    }

    /**
     * @return void
     */
    public function testARowMarksItsCodeAndItsShownChoiceThroughOneAttributeSet(): void
    {
        $field = $this->field($this->element([
            '_1' => ['code' => 'price', 'shown' => '0', 'label' => 'Cost', 'position' => '2'],
        ]));

        $this->renderCellTemplates($field);

        self::assertSame(
            [
                'option_' . $this->hash('code', 'price') => 'selected="selected"',
                'option_' . $this->hash('shown', '0') => 'selected="selected"',
            ],
            $this->extraAttributes($field, '_1')
        );
    }

    /**
     * @return void
     */
    public function testARowWithoutAShownValueIsMarkedShown(): void
    {
        $field = $this->field($this->element(['_1' => ['code' => 'position']]));

        $this->renderCellTemplates($field);

        self::assertArrayHasKey('option_' . $this->hash('shown', '1'), $this->extraAttributes($field, '_1'));
    }

    /**
     * @return void
     */
    public function testTheCellTemplatesPrintTheHashesTheRowsAskFor(): void
    {
        $field = $this->field($this->element(['_1' => ['code' => 'price', 'shown' => '0']]));

        $codeCell = $field->renderCellTemplate('code');
        $shownCell = $field->renderCellTemplate('shown');

        self::assertStringContainsString(
            '<option value="price" <%= option_extra_attrs.option_' . $this->hash('code', 'price') . ' %>',
            $codeCell
        );
        self::assertStringContainsString(
            '<option value="0" <%= option_extra_attrs.option_' . $this->hash('shown', '0') . ' %>',
            $shownCell
        );
        self::assertSame(
            ['option_' . $this->hash('code', 'price'), 'option_' . $this->hash('shown', '0')],
            array_keys($this->extraAttributes($field, '_1'))
        );
    }

    /**
     * @return void
     */
    public function testASavedCodeTheCatalogueDoesNotListStaysSelectable(): void
    {
        $field = $this->field($this->element([
            '_1' => ['code' => 'legacy', 'shown' => '1'],
            '_2' => ['code' => 'price', 'shown' => '1'],
        ]));

        $codeCell = $field->renderCellTemplate('code');
        $field->renderCellTemplate('shown');

        self::assertStringContainsString(
            '<option value="legacy" <%= option_extra_attrs.option_' . $this->hash('code', 'legacy') . ' %>',
            $codeCell
        );
        self::assertStringContainsString('legacy (not offered by any listing now)', $codeCell);
        self::assertSame(1, substr_count($codeCell, '<option value="price"'));
        self::assertArrayHasKey('option_' . $this->hash('code', 'legacy'), $this->extraAttributes($field, '_1'));
    }

    /**
     * @return void
     */
    public function testRowsThatAreNotArraysAndBlankCodesAddNoOption(): void
    {
        $field = $this->field($this->element(['__empty' => '', '_1' => 'broken', '_2' => ['code' => ''], '_3' => []]));

        $codeCell = $field->renderCellTemplate('code');

        self::assertSame(2, substr_count($codeCell, '<option '));
    }

    /**
     * @return void
     */
    public function testFailsLoudlyWhenTheLayoutBuildsTheWrongBlock(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($this->createMock(BlockInterface::class));

        $this->expectException(LocalizedException::class);

        $this->field($this->element([]), $layout);
    }

    /**
     * Build the field with its element set and its columns prepared, as the template does before rendering
     *
     * @param Text $element Configuration field element
     * @param LayoutInterface|null $layout Layout the field builds its renderers from
     * @return SortOptionOverrides
     */
    private function field(Text $element, ?LayoutInterface $layout = null): SortOptionOverrides
    {
        $context = $this->createMock(BackendContext::class);
        $context->method('getEscaper')->willReturn(new Escaper());
        $context->method('getEventManager')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getScopeConfig')->willReturn($this->createMock(ScopeConfigInterface::class));
        $context->method('getLayout')->willReturn($layout ?? $this->layout);

        $field = new class ($context, [], $this->createMock(SecureHtmlRenderer::class)) extends SortOptionOverrides {
            /**
             * Run the column setup the template triggers when rendering
             *
             * @return void
             */
            public function prepare(): void
            {
                $this->_prepareToRender();
            }
        };
        $field->setData('element', $element);
        $field->prepare();

        return $field;
    }

    /**
     * Render the cell templates the way the template does, before it reads the rows
     *
     * @param SortOptionOverrides $field Prepared field
     * @return void
     */
    private function renderCellTemplates(SortOptionOverrides $field): void
    {
        $field->renderCellTemplate('code');
        $field->renderCellTemplate('shown');
    }

    /**
     * Read the option attributes the field set on a saved row
     *
     * @param SortOptionOverrides $field Field whose cell templates are rendered
     * @param string $rowId Saved row id
     * @return array<mixed>
     */
    private function extraAttributes(SortOptionOverrides $field, string $rowId): array
    {
        $row = $field->getArrayRows()[$rowId] ?? null;
        self::assertInstanceOf(DataObject::class, $row);
        $attributes = $row->getData('option_extra_attrs');
        self::assertIsArray($attributes);

        return $attributes;
    }

    /**
     * Read the definition of a column
     *
     * @param SortOptionOverrides $field Prepared field
     * @param string $name Column name
     * @return array<mixed>
     */
    private function column(SortOptionOverrides $field, string $name): array
    {
        $column = $field->getColumns()[$name] ?? null;
        self::assertIsArray($column);

        return $column;
    }

    /**
     * Read the label of a column
     *
     * @param SortOptionOverrides $field Prepared field
     * @param string $column Column name
     * @return string
     */
    private function columnLabel(SortOptionOverrides $field, string $column): string
    {
        $label = $this->column($field, $column)['label'] ?? null;
        self::assertInstanceOf(Phrase::class, $label);

        return $label->render();
    }

    /**
     * Build a configuration field element the way the configuration form does, holding the saved rows
     *
     * @param array<string,mixed> $rows Saved rows
     * @return Text
     */
    private function element(array $rows): Text
    {
        $elementFactory = $this->createMock(Factory::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);

        $element = new Text($elementFactory, $collectionFactory, new Escaper());
        $element->setForm(new Form($elementFactory, $collectionFactory, $this->createMock(FormKey::class)));
        $element->setData('name', self::ELEMENT_NAME);
        $element->setData('value', $rows);

        return $element;
    }

    /**
     * Compute the option hash a renderer prints for a value, from the name and id the field gives that column
     *
     * @param string $column Column name
     * @param string $value Option value
     * @return string
     */
    private function hash(string $column, string $value): string
    {
        $name = self::ELEMENT_NAME . '[<%- _id %>][' . $column . ']';
        $id = '<%- _id %>_' . $column;

        return sprintf('%u', crc32($name . $id . $value));
    }
}
