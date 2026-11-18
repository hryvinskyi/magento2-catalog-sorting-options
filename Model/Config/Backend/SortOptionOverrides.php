<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model\Config\Backend;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionOverridesParserInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Exception\DuplicateSortOptionOverrideException;
use Hryvinskyi\CatalogSortingOptions\Exception\InvalidSortOptionOverridesException;
use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Backend model of the sort options setting: refuses rows the storefront could not use
 *
 * The posted rows are read the way the storefront reads them stored, so a save is refused when a row has no
 * option, has a place in list that is neither blank nor a whole number from 1, or names one option twice.
 */
class SortOptionOverrides extends ArraySerialized
{
    /**
     * Key under which the admin form posts the blank row of an emptied table
     */
    private const EMPTY_ROW_KEY = '__empty';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param SortOptionOverridesParserInterface $parser Turns the posted rows into overrides
     * @param SortOptionPolicyInterfaceFactory $policyFactory Builds the policy that refuses a repeated option
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly SortOptionOverridesParserInterface $parser,
        private readonly SortOptionPolicyInterfaceFactory $policyFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?Json $serializer = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data,
            $serializer
        );
    }

    /**
     * Refuse rows that are unreadable or name an option twice, then serialize them
     *
     * @return $this
     * @throws LocalizedException When a row has no option, has a bad place in list, or repeats an option
     */
    public function beforeSave()
    {
        $value = $this->getData('value');
        if (is_array($value)) {
            $this->assertValid(array_diff_key($value, [self::EMPTY_ROW_KEY => true]));
        }

        return parent::beforeSave();
    }

    /**
     * Check that the rows parse into overrides and that no option is listed twice
     *
     * @param array<array-key,mixed> $rows Row id => row, without the blank-table marker
     * @return void
     * @throws LocalizedException
     */
    private function assertValid(array $rows): void
    {
        try {
            $this->policyFactory->create(['overrides' => $this->parser->parse($rows)]);
        } catch (DuplicateSortOptionOverrideException $exception) {
            throw new LocalizedException(
                __(
                    'Sort option "%1" is listed more than once. Keep one row per option.',
                    $exception->getSortOptionCode()
                ),
                $exception
            );
        } catch (InvalidSortOptionOverridesException $exception) {
            throw new LocalizedException(
                __('Every sort option row needs an option, and Place in List must be blank or a whole number from 1.'),
                $exception
            );
        }
    }
}
