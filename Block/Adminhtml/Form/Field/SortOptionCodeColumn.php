<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Block\Adminhtml\Form\Field;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionCatalogueInterface;
use Magento\Framework\View\Element\Context;

/**
 * Select of sort option codes for the "Sort Option" column of the sort option overrides field
 *
 * Offers every option of the catalogue, then each code already saved in the field that the catalogue no longer
 * lists, so saving the form again never turns a saved row into a different code.
 */
class SortOptionCodeColumn extends AbstractSelectColumn
{
    /**
     * @var list<string>
     */
    private array $storedCodes = [];

    /**
     * @param Context $context Block context
     * @param SortOptionCatalogueInterface $catalogue Sort options an admin can pick from
     * @param array<string,mixed> $data Block data
     */
    public function __construct(
        Context $context,
        private readonly SortOptionCatalogueInterface $catalogue,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Remember the codes already saved in the field, so they stay selectable
     *
     * @param list<string> $codes Saved option codes
     * @return $this
     */
    public function setStoredCodes(array $codes): static
    {
        $this->storedCodes = $codes;
        $this->setOptions([]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if ($this->getOptions() === []) {
            $this->setOptions($this->buildOptions());
        }

        return parent::_toHtml();
    }

    /**
     * Build the select options: the catalogue's, then the saved codes the catalogue does not list
     *
     * @return list<array{value: string, label: string}>
     */
    private function buildOptions(): array
    {
        $options = [];
        $offered = [];
        foreach ($this->catalogue->getSortOptions() as $code => $label) {
            $offered[(string)$code] = true;
            $options[] = ['value' => (string)$code, 'label' => $label];
        }
        foreach ($this->storedCodes as $code) {
            if (isset($offered[$code])) {
                continue;
            }
            $offered[$code] = true;
            $options[] = ['value' => $code, 'label' => (string)__('%1 (not offered by any listing now)', $code)];
        }

        return $options;
    }
}
