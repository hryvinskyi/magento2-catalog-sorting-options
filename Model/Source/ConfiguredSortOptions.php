<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model\Source;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Offers the sort options given as a `di.xml` array, code => label
 *
 * A module that adds a sort code to listings at runtime exposes it to the admin without a PHP class: a
 * `virtualType` of this class holds the options (label items marked `translate="true"`), and one item in the
 * `sources` pool of the option catalogue points at the virtual type. It is not in the pool by default.
 * A label may arrive as a string or as a translated phrase; it is kept as a string.
 */
class ConfiguredSortOptions implements SortOptionSourceInterface
{
    /**
     * @var array<string, string>
     */
    private readonly array $options;

    /**
     * @param array<mixed> $options Option code => label
     * @throws \InvalidArgumentException When a code or a label is not a non-blank string
     */
    public function __construct(array $options = [])
    {
        $this->options = $this->validOptions($options);
    }

    /**
     * @inheritDoc
     */
    public function getSortOptions(): array
    {
        return $this->options;
    }

    /**
     * Keep the options as code => label strings
     *
     * @param array<mixed> $options Option code => label, as configured
     * @return array<string, string>
     * @throws \InvalidArgumentException When a code or a label is not a non-blank string
     */
    private function validOptions(array $options): array
    {
        $valid = [];
        foreach ($options as $code => $label) {
            $code = (string)$code;
            if ($label instanceof Phrase) {
                $label = $label->render();
            }
            if (trim($code) === '' || !is_string($label) || trim($label) === '') {
                throw new \InvalidArgumentException(sprintf(
                    'Sort option "%s" needs a non-blank code and a non-blank label.',
                    $code
                ));
            }
            $valid[$code] = $label;
        }

        return $valid;
    }
}
