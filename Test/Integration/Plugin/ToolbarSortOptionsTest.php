<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Integration\Plugin;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Api\DefaultDirectionResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionCatalogueInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionOverridesParserInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\RegistersListingSortOption;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\ResolvesServices;
use Hryvinskyi\CatalogSortingOptions\Test\Integration\TestListingSortOption;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The sort options a product listing toolbar offers follow the settings stored for the store, and the listing sort
 * options a store offers join them and open in their own direction
 *
 * Every scenario runs on a fresh application: the policy resolver, the toolbar memorizer and the request are shared
 * instances, so a second scenario in one process would read the first one's settings and URL values. Settings are
 * written at store scope through config attributes and every assertion reads a block created for that assertion,
 * because the toolbar remembers its current order and direction.
 */
#[AppArea('frontend')]
class ToolbarSortOptionsTest extends TestCase
{
    use ResolvesServices;
    use RegistersListingSortOption;

    private const HIDE_POSITION_RENAME_PRICE = '{"row_1":{"code":"position","shown":"0","label":"","position":""},'
        . '"row_2":{"code":"price","shown":"1","label":"Cost","position":""}}';

    private const PRICE_FIRST = '{"row_1":{"code":"price","shown":"1","label":"","position":"1"}}';

    private const HIDE_POSITION = '{"row_1":{"code":"position","shown":"0","label":"","position":""}}';

    private const HIDE_PRICE = '{"row_1":{"code":"price","shown":"0","label":"","position":""}}';

    private const HIDE_EVERYTHING_RENAME_PRICE = '{"row_1":{"code":"position","shown":"0","label":"","position":""},'
        . '"row_2":{"code":"name","shown":"0","label":"","position":""},'
        . '"row_3":{"code":"price","shown":"0","label":"Cost","position":""}}';

    private const HIDE_POSITION_PRICE_FIRST_RENAMED =
        '{"row_1":{"code":"position","shown":"0","label":"","position":""},'
        . '"row_2":{"code":"price","shown":"1","label":"Cost","position":"1"}}';

    private const HIDE_RELEVANCE = '{"row_1":{"code":"relevance","shown":"0","label":"","position":""}}';

    private const RENAME_NAME_ONLY = '{"row_1":{"code":"name","shown":"1","label":"Title","position":""}}';

    private const STORE_CODE = 'default';

    /**
     * @return void
     */
    #[AppIsolation(true)]
    public function testEveryContractResolves(): void
    {
        $this->service(SortOptionPolicyInterface::class);
        $this->service(SortOptionOverridesParserInterface::class);
        $this->service(SortOptionPolicyResolverInterface::class);
        $this->service(ListingSortOptionsInterface::class);
        $this->service(DefaultDirectionResolverInterface::class);

        $override = $this->service(SortOptionOverrideInterfaceFactory::class)
            ->create(['code' => 'price', 'shown' => false]);
        $this->assertSame('price', $override->getCode());

        $policy = $this->service(SortOptionPolicyInterfaceFactory::class)->create(['overrides' => [$override]]);
        $this->assertTrue($policy->hides('price'));

        $options = $this->service(SortOptionCatalogueInterface::class)->getSortOptions();
        $this->assertArrayHasKey('position', $options);
        $this->assertArrayHasKey('relevance', $options);
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_POSITION_RENAME_PRICE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAHiddenOptionIsRemovedAndARenamedOneShowsItsNewLabel(): void
    {
        $toolbar = $this->createToolbar(['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price']);

        $this->assertSame(['name' => 'Product Name', 'price' => 'Cost'], $toolbar->getAvailableOrders());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::PRICE_FIRST,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAnOptionWithAPlaceInListMovesToThatPlaceAndTheOthersKeepTheirOrder(): void
    {
        $toolbar = $this->createToolbar(['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price']);

        $this->assertSame(
            ['price' => 'Price', 'position' => 'Position', 'name' => 'Product Name'],
            $toolbar->getAvailableOrders()
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_POSITION,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAHiddenDefaultOrderFallsBackToTheFirstShownOption(): void
    {
        $toolbar = $this->createToolbar(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price'],
            'position'
        );

        $this->assertSame('name', $toolbar->getCurrentOrder());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_POSITION,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAHiddenOrderTakenFromTheUrlIsIgnored(): void
    {
        $this->requestParameter('product_list_order', 'position');
        $toolbar = $this->createToolbar(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price'],
            'position'
        );

        $this->assertSame('name', $toolbar->getCurrentOrder());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_POSITION,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAShownOrderTakenFromTheUrlIsKept(): void
    {
        $this->requestParameter('product_list_order', 'price');
        $toolbar = $this->createToolbar(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price'],
            'position'
        );

        $this->assertSame('price', $toolbar->getCurrentOrder());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config('catalog/frontend/default_sort_by', 'price', ScopeInterface::SCOPE_STORE, self::STORE_CODE)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_PRICE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAHiddenConfiguredDefaultOrderIsReplacedAndSortsAscending(): void
    {
        $toolbar = $this->createToolbar(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price'],
            null,
            'desc'
        );

        $this->assertSame('position', $toolbar->getCurrentOrder());
        $this->assertSame('asc', $toolbar->getCurrentDirection());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_EVERYTHING_RENAME_PRICE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAListingNeverLosesAllItsOptionsButStillShowsTheNewLabels(): void
    {
        $toolbar = $this->createToolbar(['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price']);

        $this->assertSame(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Cost'],
            $toolbar->getAvailableOrders()
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_POSITION_PRICE_FIRST_RENAMED,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testCodeThatReadsTheListEditsItAndSetsItBackStillGetsTheSettingsApplied(): void
    {
        $toolbar = $this->createToolbar(
            ['position' => 'Position', 'name' => 'Product Name', 'price' => 'Price', 'rating' => 'Rating']
        );

        $orders = $toolbar->getAvailableOrders();
        $this->assertSame(['price' => 'Cost', 'name' => 'Product Name', 'rating' => 'Rating'], $orders);

        unset($orders['name']);
        $orders['relevance'] = 'Relevance';
        $orders['position'] = 'Position';
        $toolbar->setAvailableOrders($orders);

        $this->assertSame(
            ['price' => 'Cost', 'rating' => 'Rating', 'relevance' => 'Relevance'],
            $toolbar->getAvailableOrders()
        );
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_RELEVANCE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAHiddenSearchDefaultIsReplacedByTheFirstShownOption(): void
    {
        $toolbar = $this->createSearchToolbar();

        $this->assertSame('name', $toolbar->getCurrentOrder());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_RELEVANCE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testAHiddenSearchDefaultSortsAscendingInsteadOfDescending(): void
    {
        $toolbar = $this->createSearchToolbar();

        $this->assertSame('asc', $toolbar->getCurrentDirection());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_RELEVANCE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testADirectionTheShopperChoseIsKeptWhenTheDefaultIsReplaced(): void
    {
        $this->requestParameter('product_list_dir', 'desc');
        $toolbar = $this->createSearchToolbar();

        $this->assertSame('desc', $toolbar->getCurrentDirection());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_RELEVANCE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testTheToolbarScriptReceivesTheReplacementDefaults(): void
    {
        $toolbar = $this->createSearchToolbar();

        $options = $this->widgetOptions($toolbar->getWidgetOptionsJson());

        $this->assertSame('name', $options['orderDefault']);
        $this->assertSame('asc', $options['directionDefault']);
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::RENAME_NAME_ONLY,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testASearchListingThatHidesNothingKeepsItsDescendingDefault(): void
    {
        $toolbar = $this->createSearchToolbar();

        $this->assertSame('relevance', $toolbar->getCurrentOrder());
        $this->assertSame('desc', $toolbar->getCurrentDirection());
        $this->assertSame($this->widgetJsonWithoutPlugins($toolbar), $toolbar->getWidgetOptionsJson());

        $options = $this->widgetOptions($toolbar->getWidgetOptionsJson());
        $this->assertSame('relevance', $options['orderDefault']);
        $this->assertSame('desc', $options['directionDefault']);
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(
        SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES,
        self::HIDE_PRICE,
        ScopeInterface::SCOPE_STORE,
        self::STORE_CODE
    )]
    public function testASearchListingWhoseDefaultStaysShownKeepsItsDefaultsWhenAnotherOptionIsHidden(): void
    {
        $toolbar = $this->createSearchToolbar();

        $this->assertSame('relevance', $toolbar->getCurrentOrder());
        $this->assertSame('desc', $toolbar->getCurrentDirection());
        $this->assertSame($this->widgetJsonWithoutPlugins($toolbar), $toolbar->getWidgetOptionsJson());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(TestListingSortOption::ENABLED_PATH, '1', ScopeInterface::SCOPE_STORE, self::STORE_CODE)]
    public function testAListingSortOptionTheStoreOffersIsListedByAToolbarThatStartsFromTheCatalogOptions(): void
    {
        $this->registerListingSortOption();

        $orders = $this->createToolbarFromCatalogOptions()->getAvailableOrders();

        $this->assertArrayHasKey('position', $orders);
        $this->assertSame(TestListingSortOption::LABEL, $orders[TestListingSortOption::CODE] ?? null);
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(TestListingSortOption::ENABLED_PATH, '1', ScopeInterface::SCOPE_STORE, self::STORE_CODE)]
    public function testAListingSortOptionChosenWithoutADirectionSortsDescendingInTheToolbarAndItsScript(): void
    {
        $this->registerListingSortOption();
        $this->requestParameter('product_list_order', TestListingSortOption::CODE);
        $toolbar = $this->createToolbarFromCatalogOptions();

        $this->assertSame(TestListingSortOption::CODE, $toolbar->getCurrentOrder());
        $this->assertSame('desc', $toolbar->getCurrentDirection());
        $this->assertSame('desc', $this->widgetOptions($toolbar->getWidgetOptionsJson())['directionDefault']);
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(TestListingSortOption::ENABLED_PATH, '1', ScopeInterface::SCOPE_STORE, self::STORE_CODE)]
    public function testAListingSortOptionKeepsTheAscendingDirectionTheShopperChose(): void
    {
        $this->registerListingSortOption();
        $this->requestParameter('product_list_order', TestListingSortOption::CODE);
        $this->requestParameter('product_list_dir', 'asc');
        $toolbar = $this->createToolbarFromCatalogOptions();

        $this->assertSame(TestListingSortOption::CODE, $toolbar->getCurrentOrder());
        $this->assertSame('asc', $toolbar->getCurrentDirection());
    }

    /**
     * @return void
     */
    #[AppIsolation(true)]
    #[Config(TestListingSortOption::ENABLED_PATH, '0', ScopeInterface::SCOPE_STORE, self::STORE_CODE)]
    public function testAListingSortOptionTheStoreDoesNotOfferIsNeitherListedNorUsedFromTheUrl(): void
    {
        $this->registerListingSortOption();
        $this->requestParameter('product_list_order', TestListingSortOption::CODE);
        $toolbar = $this->createToolbarFromCatalogOptions();

        $orders = $toolbar->getAvailableOrders();
        $this->assertArrayNotHasKey(TestListingSortOption::CODE, $orders);
        $this->assertNotSame(TestListingSortOption::CODE, $toolbar->getCurrentOrder());
    }

    /**
     * @param string $enabled Value of the option's setting in the store
     * @param list<string> $expected Codes the category lists
     * @return void
     * @dataProvider categoryOwnListProvider
     */
    #[AppIsolation(true)]
    #[DataProvider('categoryOwnListProvider')]
    public function testACategoryThatNamesAListingSortOptionListsItOnlyWhereTheStoreOffersIt(
        string $enabled,
        array $expected
    ): void {
        $this->setStoreConfig(TestListingSortOption::ENABLED_PATH, $enabled);
        $this->registerListingSortOption();
        $category = $this->service(CategoryFactory::class)->create();
        $category->setData('available_sort_by', ['name', TestListingSortOption::CODE]);

        $this->assertSame($expected, array_keys($category->getAvailableSortByOptions()));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function categoryOwnListProvider(): array
    {
        return [
            'offered' => ['1', ['name', TestListingSortOption::CODE]],
            'not offered' => ['0', ['name']],
        ];
    }

    /**
     * Create a toolbar block the way a category listing configures it
     *
     * @param array<string, string> $orders Available orders, option code => label
     * @param string|null $defaultOrder Default order to request, none when null
     * @param string|null $defaultDirection Default direction to request, none when null
     * @return Toolbar
     */
    private function createToolbar(
        array $orders,
        ?string $defaultOrder = null,
        ?string $defaultDirection = null
    ): Toolbar {
        $layout = $this->service(LayoutInterface::class);
        $toolbar = $layout->createBlock(Toolbar::class);
        $this->assertInstanceOf(Toolbar::class, $toolbar);

        $toolbar->setAvailableOrders($orders);
        if ($defaultOrder !== null) {
            $toolbar->setDefaultOrder($defaultOrder);
        }
        if ($defaultDirection !== null) {
            $toolbar->setDefaultDirection($defaultDirection);
        }

        return $toolbar;
    }

    /**
     * Create a toolbar block that nobody gave options, so it starts from the catalog's sortable options
     *
     * @return Toolbar
     */
    private function createToolbarFromCatalogOptions(): Toolbar
    {
        $toolbar = $this->service(LayoutInterface::class)->createBlock(Toolbar::class);
        $this->assertInstanceOf(Toolbar::class, $toolbar);

        return $toolbar;
    }

    /**
     * Write a setting at the scope of the test store for the rest of the test
     *
     * @param string $path Config path
     * @param string $value Value
     * @return void
     */
    private function setStoreConfig(string $path, string $value): void
    {
        $this->service(MutableScopeConfigInterface::class)
            ->setValue($path, $value, ScopeInterface::SCOPE_STORE, self::STORE_CODE);
    }

    /**
     * Create a toolbar block the way a quick search result configures it: relevance first by default, best first
     *
     * @return Toolbar
     */
    private function createSearchToolbar(): Toolbar
    {
        return $this->createToolbar(
            ['name' => 'Product Name', 'price' => 'Price', 'relevance' => 'Relevance'],
            'relevance',
            'desc'
        );
    }

    /**
     * Set a parameter on the request the toolbar reads, before any block reads it
     *
     * @param string $name Parameter name
     * @param string $value Parameter value
     * @return void
     */
    private function requestParameter(string $name, string $value): void
    {
        $request = $this->service(RequestInterface::class);
        $this->assertInstanceOf(HttpRequest::class, $request);
        $request->setParam($name, $value);
    }

    /**
     * Get the toolbar script options the toolbar prints when no plugin touches them
     *
     * @param Toolbar $toolbar Toolbar block
     * @return string
     */
    private function widgetJsonWithoutPlugins(Toolbar $toolbar): string
    {
        $parentCall = new \ReflectionMethod($toolbar, '___callParent');
        $json = $parentCall->invoke($toolbar, 'getWidgetOptionsJson', []);
        $this->assertIsString($json);

        return $json;
    }

    /**
     * Get the options of the toolbar script out of its JSON
     *
     * @param string $json Toolbar script options as JSON
     * @return array<string, mixed>
     */
    private function widgetOptions(string $json): array
    {
        $decoded = $this->service(Json::class)->unserialize($json);
        $this->assertIsArray($decoded);
        $options = $decoded['productListToolbarForm'] ?? null;
        $this->assertIsArray($options);

        return array_combine(array_map('strval', array_keys($options)), $options);
    }
}
