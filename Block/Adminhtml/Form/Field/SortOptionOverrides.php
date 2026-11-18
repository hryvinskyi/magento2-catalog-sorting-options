<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\BlockInterface;

/**
 * Dynamic-rows admin field listing what a store changes about its sort options
 *
 * One row per sort option: whether it is shown, its label and its place in the list. The option and the
 * Yes/No selects are rendered by column renderers, and each saved row marks its own choices through one set of
 * option attributes that carries both selections.
 */
class SortOptionOverrides extends AbstractFieldArray
{
    /**
     * @var SortOptionCodeColumn|null
     */
    private ?SortOptionCodeColumn $codeRenderer = null;

    /**
     * @var ShownColumn|null
     */
    private ?ShownColumn $shownRenderer = null;

    /**
     * @inheritDoc
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('code', [
            'label' => __('Sort Option'),
            'renderer' => $this->getCodeRenderer(),
        ]);
        $this->addColumn('shown', [
            'label' => __('Show'),
            'renderer' => $this->getShownRenderer(),
        ]);
        $this->addColumn('label', [
            'label' => __('Label'),
        ]);
        $this->addColumn('position', [
            'label' => __('Place in List'),
            'class' => 'validate-digits validate-greater-than-zero',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add Sort Option');
    }

    /**
     * Mark the saved choices of a row as selected
     *
     * Both selections go into one option attribute set: a second assignment would replace the first. The keys
     * are the hashes the renderers print in the cell templates, so the option hash of each renderer must be
     * asked after the field has handed them their input name and id.
     *
     * @param DataObject $row Saved row
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $code = $row->getData('code');
        $shown = $row->getData('shown');
        $isHidden = in_array($shown, ['0', 0, false], true);

        $row->setData('option_extra_attrs', [
            'option_' . $this->getCodeRenderer()->calcOptionHash(is_string($code) ? $code : '')
                => 'selected="selected"',
            'option_' . $this->getShownRenderer()->calcOptionHash($isHidden ? '0' : '1')
                => 'selected="selected"',
        ]);
    }

    /**
     * Lazily build the option select renderer, handing it the codes already saved in this field
     *
     * @return SortOptionCodeColumn
     * @throws LocalizedException
     */
    private function getCodeRenderer(): SortOptionCodeColumn
    {
        if ($this->codeRenderer === null) {
            $renderer = $this->getLayout()->createBlock(
                SortOptionCodeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->codeRenderer = $this->codeColumn($renderer)->setStoredCodes($this->readStoredCodes());
        }

        return $this->codeRenderer;
    }

    /**
     * Lazily build the Yes/No select renderer
     *
     * @return ShownColumn
     * @throws LocalizedException
     */
    private function getShownRenderer(): ShownColumn
    {
        if ($this->shownRenderer === null) {
            $renderer = $this->getLayout()->createBlock(
                ShownColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->shownRenderer = $this->shownColumn($renderer);
        }

        return $this->shownRenderer;
    }

    /**
     * Accept the block the layout built for the option column only when it is that column
     *
     * The layout builds whatever class the object manager is configured to give for the column, so a preference
     * for another block would otherwise fail later with a call to a method the block does not have.
     *
     * @param BlockInterface $block Block the layout built
     * @return SortOptionCodeColumn
     * @throws LocalizedException When the block is not the option column
     */
    private function codeColumn(BlockInterface $block): SortOptionCodeColumn
    {
        if (!$block instanceof SortOptionCodeColumn) {
            throw new LocalizedException(__('The sort option column could not be created.'));
        }

        return $block;
    }

    /**
     * Accept the block the layout built for the Yes/No column only when it is that column
     *
     * @param BlockInterface $block Block the layout built
     * @return ShownColumn
     * @throws LocalizedException When the block is not the Yes/No column
     */
    private function shownColumn(BlockInterface $block): ShownColumn
    {
        if (!$block instanceof ShownColumn) {
            throw new LocalizedException(__('The show column could not be created.'));
        }

        return $block;
    }

    /**
     * Read the option codes saved in the rows of this field
     *
     * @return list<string>
     */
    private function readStoredCodes(): array
    {
        $element = $this->getData('element');
        $rows = $element instanceof AbstractElement ? $element->getData('value') : null;
        if (!is_array($rows)) {
            return [];
        }

        $codes = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['code']) && is_string($row['code']) && $row['code'] !== '') {
                $codes[] = $row['code'];
            }
        }

        return $codes;
    }
}
