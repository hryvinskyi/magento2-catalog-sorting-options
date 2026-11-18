<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Integration\Model\Config\Backend;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionOverridesParserInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\ResolvesServices;
use Magento\Config\Model\ConfigFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

/**
 * Saving the sort options setting through the admin configuration refuses rows the storefront could not use
 *
 * The configuration structure, and with it this field's backend model, is read in the admin area, so the save goes
 * through the same configuration model the admin form uses.
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class SortOptionOverridesTest extends TestCase
{
    use ResolvesServices;

    private const STORE_CODE = 'default';

    /**
     * @return void
     */
    public function testTwoRowsForOneOptionAreRefusedNamingTheOption(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Sort option "price" is listed more than once');

        $this->save([
            'row_1' => $this->row('price', '1', 'Cost', ''),
            'row_2' => $this->row('price', '0', '', ''),
            '__empty' => '',
        ]);
    }

    /**
     * @return void
     */
    public function testARowWithoutAnOptionIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Every sort option row needs an option');

        $this->save(['row_1' => $this->row('', '0', '', ''), '__empty' => '']);
    }

    /**
     * @return void
     */
    public function testAPlaceInListThatIsNotAWholeNumberFromOneIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Place in List must be blank or a whole number from 1');

        $this->save(['row_1' => $this->row('price', '1', '', '0'), '__empty' => '']);
    }

    /**
     * @return void
     */
    public function testAValidRowIsStoredAndTheStorefrontPolicyReadsItBack(): void
    {
        $this->save([
            'row_1' => $this->row('price', '0', 'Cost', '2'),
            '__empty' => '',
        ]);

        $stored = $this->service(ScopeConfigInterface::class)->getValue(
            SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
            ScopeInterface::SCOPE_STORE,
            self::STORE_CODE
        );
        $this->assertIsString($stored);

        $rows = $this->service(Json::class)->unserialize($stored);
        $this->assertIsArray($rows);
        $overrides = $this->service(SortOptionOverridesParserInterface::class)->parse($rows);

        $this->assertCount(1, $overrides);
        $this->assertSame('price', $overrides[0]->getCode());
        $this->assertFalse($overrides[0]->isShown());
        $this->assertSame('Cost', $overrides[0]->getLabel());
        $this->assertSame(2, $overrides[0]->getPosition());

        $storeId = (int) $this->service(StoreManagerInterface::class)->getStore(self::STORE_CODE)->getId();
        $policy = $this->service(SortOptionPolicyResolverInterface::class)->forStore($storeId);
        $this->assertTrue($policy->hides('price'));
    }

    /**
     * Save the sort options setting at store scope the way the admin configuration form does
     *
     * @param array<string, array<string, string>|string> $rows Posted rows, row id => row, with the blank-table marker
     * @return void
     * @throws LocalizedException When the backend model refuses the rows
     */
    private function save(array $rows): void
    {
        $config = $this->service(ConfigFactory::class)->create();
        $config->setSection('catalog_sorting');
        $config->setStore(self::STORE_CODE);
        $config->setData('groups', ['options' => ['fields' => ['overrides' => ['value' => $rows]]]]);
        $config->save();
    }

    /**
     * Build a posted row of the sort options table
     *
     * @param string $code Option code
     * @param string $shown '1' to show the option, '0' to hide it
     * @param string $label New label, blank keeps the current one
     * @param string $position Place in list, blank keeps the place
     * @return array<string, string>
     */
    private function row(string $code, string $shown, string $label, string $position): array
    {
        return ['code' => $code, 'shown' => $shown, 'label' => $label, 'position' => $position];
    }
}
