<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Plugin\Catalog;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Model\DefaultDirectionResolver;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\ToolbarDefaultDirectionPlugin;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Helper\Product\ProductList;
use Magento\Catalog\Model\Product\ProductList\ToolbarMemorizer;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The toolbar, its script and the page cache get the effective default direction of the current order: a listing
 * sort option's own direction, else ascending on a listing whose hidden default order is replaced, else the
 * toolbar's own. A hidden default order is replaced by the first shown option in the script too. A direction the
 * shopper chose always wins, and every check that fails returns the toolbar's own value before the next one runs.
 *
 * The plugin runs with the real direction resolver, so these tests pin the rules as a shopper sees them.
 */
class ToolbarDefaultDirectionPluginTest extends TestCase
{
    /**
     * Options the listing shows after the policy hid relevance
     */
    private const SHOWN = ['name' => 'Product Name', 'price' => 'Price'];

    /**
     * URL parameter of the sort direction
     */
    private const DIRECTION_PARAM = 'product_list_dir';

    /**
     * A listing sort option that opens descending
     */
    private const LISTING_OPTION = 'bestsellers';

    /**
     * @var SortOptionPolicyResolverInterface&MockObject
     */
    private $resolver;

    /**
     * @var ListingSortOptionsInterface&MockObject
     */
    private $listingSortOptions;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var ToolbarMemorizer&MockObject
     */
    private $memorizer;

    /**
     * @var ProductList&MockObject
     */
    private $productListHelper;

    /**
     * @var HttpContext&MockObject
     */
    private $httpContext;

    /**
     * @var SortOptionPolicyInterface&MockObject
     */
    private $policy;

    /**
     * @var Toolbar&MockObject
     */
    private $toolbar;

    /**
     * @var string|null The toolbar's current order
     */
    private ?string $currentOrder = 'name';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(SortOptionPolicyResolverInterface::class);
        $this->listingSortOptions = $this->createMock(ListingSortOptionsInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->memorizer = $this->createMock(ToolbarMemorizer::class);
        $this->productListHelper = $this->createMock(ProductList::class);
        $this->httpContext = $this->createMock(HttpContext::class);
        $this->policy = $this->createMock(SortOptionPolicyInterface::class);
        $this->toolbar = $this->createMock(Toolbar::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->resolver->method('forStore')->with(3)->willReturn($this->policy);
        $this->toolbar->method('getCurrentOrder')->willReturnCallback(fn (): ?string => $this->currentOrder);
        $this->listingSortOptions->method('defaultDirectionOf')->willReturnCallback(
            static fn (string $code): ?SortDirection => $code === self::LISTING_OPTION
                ? SortDirection::Descending
                : null
        );
    }

    /**
     * @return void
     */
    public function testRequestedDefaultOrderIsRecordedInTheBlockAndTheResultReturned(): void
    {
        $this->toolbar->expects(self::once())
            ->method('setData')
            ->with(ToolbarDefaultDirectionPlugin::DEFAULT_ORDER_DATA_KEY, 'relevance');

        self::assertSame(
            $this->toolbar,
            $this->plugin()->afterSetDefaultOrder($this->toolbar, $this->toolbar, 'relevance')
        );
    }

    /**
     * @param mixed $field Requested default order
     * @return void
     * @dataProvider blankFieldProvider
     */
    #[DataProvider('blankFieldProvider')]
    public function testBlankOrNonStringDefaultOrderIsNotRecorded(mixed $field): void
    {
        $this->toolbar->expects(self::never())->method('setData');

        self::assertSame(
            $this->toolbar,
            $this->plugin()->afterSetDefaultOrder($this->toolbar, $this->toolbar, $field)
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function blankFieldProvider(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['  '],
            'null' => [null],
            'integer' => [5],
        ];
    }

    /**
     * @return void
     */
    public function testDirectionIsKeptWithoutFurtherLookupsWhenThePolicyHidesNothing(): void
    {
        $this->policy->method('hidesAnything')->willReturn(false);
        $this->policy->expects(self::never())->method('hides');
        $this->policy->expects(self::never())->method('replacementForDefault');
        $this->toolbar->expects(self::never())->method('getData');
        $this->toolbar->expects(self::never())->method('getAvailableOrders');
        $this->productListHelper->expects(self::never())->method('getDefaultSortField');
        $this->memorizer->expects(self::never())->method('getDirection');
        $this->httpContext->expects(self::never())->method('setValue');

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testDirectionIsKeptWhenNoDefaultOrderIsKnown(): void
    {
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn(null);
        $this->productListHelper->method('getDefaultSortField')->willReturn(null);
        $this->policy->expects(self::never())->method('hides');
        $this->memorizer->expects(self::never())->method('getDirection');
        $this->httpContext->expects(self::never())->method('setValue');

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testDirectionIsKeptWithoutReadingTheOptionsWhenTheDefaultOrderIsNotHidden(): void
    {
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn('relevance');
        $this->policy->expects(self::once())->method('hides')->with('relevance')->willReturn(false);
        $this->toolbar->expects(self::never())->method('getAvailableOrders');
        $this->policy->expects(self::never())->method('replacementForDefault');
        $this->memorizer->expects(self::never())->method('getDirection');
        $this->httpContext->expects(self::never())->method('setValue');

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testDirectionIsKeptAndNothingVariedWhenTheListingStillShowsTheHiddenDefault(): void
    {
        $this->hiddenRecordedDefault();
        $this->memorizer->method('isMemorizingAllowed')->willReturn(true);
        $this->toolbar->method('getAvailableOrders')->willReturn(['relevance' => 'Relevance']);
        $this->policy->method('replacementForDefault')
            ->with('relevance', ['relevance' => 'Relevance'])
            ->willReturn(null);
        $this->memorizer->expects(self::never())->method('getDirection');
        $this->httpContext->expects(self::never())->method('setValue');

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @param string $chosen Direction the shopper chose
     * @return void
     * @dataProvider chosenDirectionProvider
     */
    #[DataProvider('chosenDirectionProvider')]
    public function testDirectionTheShopperChoseIsKeptWhenTheDefaultIsReplaced(string $chosen): void
    {
        $this->hiddenRecordedDefault();
        $this->replacedByName();
        $this->memorizer->method('getDirection')->willReturn($chosen);

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function chosenDirectionProvider(): array
    {
        return [
            'descending' => ['desc'],
            'descending in capitals' => ['DESC'],
            'ascending, mixed case' => ['Asc'],
        ];
    }

    /**
     * @param mixed $memorized What the memorizer holds when the shopper chose no valid direction
     * @return void
     * @dataProvider noChoiceProvider
     */
    #[DataProvider('noChoiceProvider')]
    public function testReplacedDefaultSortsAscendingWhenTheShopperChoseNoDirection(mixed $memorized): void
    {
        $this->hiddenRecordedDefault();
        $this->replacedByName();
        $this->memorizer->method('getDirection')->willReturn($memorized);

        self::assertSame('asc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function noChoiceProvider(): array
    {
        return [
            'nothing' => [null],
            'empty' => [''],
            'not a direction' => ['sideways'],
            'not a string' => [false],
        ];
    }

    /**
     * @return void
     */
    public function testAnOrderOtherThanTheReplacementAlsoSortsAscendingOnAListingWithAHiddenDefault(): void
    {
        $this->currentOrder = 'price';
        $this->hiddenRecordedDefault();
        $this->replacedByName();
        $this->memorizer->method('getDirection')->willReturn(null);
        $this->memorizer->method('isMemorizingAllowed')->willReturn(true);
        $this->httpContext->expects(self::once())
            ->method('setValue')
            ->with(self::DIRECTION_PARAM, 'asc', 'asc');

        self::assertSame('asc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testRememberedDescendingDirectionVariesTheCacheAgainstTheAscendingDefault(): void
    {
        $this->hiddenRecordedDefault();
        $this->replacedByName();
        $this->memorizer->method('getDirection')->willReturn('desc');
        $this->memorizer->method('isMemorizingAllowed')->willReturn(true);
        $this->httpContext->expects(self::once())
            ->method('setValue')
            ->with(self::DIRECTION_PARAM, 'desc', 'asc');

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testAscendingDefaultIsWrittenAsTheBaselineWhenTheShopperChoseNothing(): void
    {
        $this->hiddenRecordedDefault();
        $this->replacedByName();
        $this->memorizer->method('getDirection')->willReturn(null);
        $this->memorizer->method('isMemorizingAllowed')->willReturn(true);
        $this->httpContext->expects(self::once())
            ->method('setValue')
            ->with(self::DIRECTION_PARAM, 'asc', 'asc');

        self::assertSame('asc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testNothingIsWrittenToTheHttpContextWhenTheStoreDoesNotRememberToolbarChoices(): void
    {
        $this->hiddenRecordedDefault();
        $this->replacedByName();
        $this->memorizer->method('getDirection')->willReturn('desc');
        $this->memorizer->method('isMemorizingAllowed')->willReturn(false);
        $this->httpContext->expects(self::never())->method('setValue');

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return void
     */
    public function testRecordedDefaultOrderBeatsTheConfiguredOne(): void
    {
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')
            ->with(ToolbarDefaultDirectionPlugin::DEFAULT_ORDER_DATA_KEY)
            ->willReturn('relevance');
        $this->productListHelper->expects(self::never())->method('getDefaultSortField');
        $this->policy->expects(self::once())->method('hides')->with('relevance')->willReturn(false);

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @param mixed $recorded What the block holds when no caller requested a usable default order
     * @return void
     * @dataProvider unusableRecordProvider
     */
    #[DataProvider('unusableRecordProvider')]
    public function testConfiguredDefaultOrderIsUsedWhenNoneIsRecorded(mixed $recorded): void
    {
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn($recorded);
        $this->productListHelper->expects(self::once())->method('getDefaultSortField')->willReturn('position');
        $this->policy->method('hides')->with('position')->willReturn(true);
        $this->memorizer->method('getDirection')->willReturn(null);
        $this->toolbar->method('getAvailableOrders')->willReturn(self::SHOWN);
        $this->policy->method('replacementForDefault')->with('position', self::SHOWN)->willReturn('name');

        self::assertSame('asc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'desc'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableRecordProvider(): array
    {
        return [
            'nothing recorded' => [null],
            'blank' => [' '],
        ];
    }

    /**
     * Every cell of: listing sort option current or not, shopper chose a direction or not, the store remembers
     * toolbar choices or not. The context default is the option's own direction, so an untouched page adds nothing
     * to the cache key and a remembered other direction gets an entry of its own.
     *
     * @param string $order The toolbar's current order
     * @param mixed $memorized The direction the shopper chose, as the memorizer gives it
     * @param bool $memorizing Whether the store remembers toolbar choices
     * @param string $expected Direction the toolbar should return
     * @param array{string, string}|null $context Value and default written to the HTTP context; null for none
     * @return void
     * @dataProvider listingOptionCellProvider
     */
    #[DataProvider('listingOptionCellProvider')]
    public function testAListingSortOptionOpensInItsOwnDirectionUnlessTheShopperChoseOne(
        string $order,
        mixed $memorized,
        bool $memorizing,
        string $expected,
        ?array $context
    ): void {
        $this->currentOrder = $order;
        $this->policy->method('hidesAnything')->willReturn(false);
        $this->memorizer->method('getDirection')->willReturn($memorized);
        $this->memorizer->method('isMemorizingAllowed')->willReturn($memorizing);
        if ($context === null) {
            $this->httpContext->expects(self::never())->method('setValue');
        } else {
            $this->httpContext->expects(self::once())
                ->method('setValue')
                ->with(self::DIRECTION_PARAM, $context[0], $context[1]);
        }

        // The toolbar resolved "asc" itself: its own default, or the shopper's ascending choice.
        self::assertSame($expected, $this->plugin()->afterGetCurrentDirection($this->toolbar, 'asc'));
    }

    /**
     * @return array<string, array{string, mixed, bool, string, array{string, string}|null}>
     */
    public static function listingOptionCellProvider(): array
    {
        return [
            'option, no choice, remembering' => [self::LISTING_OPTION, null, true, 'desc', ['desc', 'desc']],
            'option, no choice, not remembering' => [self::LISTING_OPTION, null, false, 'desc', null],
            'option, chose ascending, remembering' => [self::LISTING_OPTION, 'asc', true, 'asc', ['asc', 'desc']],
            'option, chose ascending, not remembering' => [self::LISTING_OPTION, 'asc', false, 'asc', null],
            'other order, no choice, remembering' => ['price', null, true, 'asc', null],
            'other order, no choice, not remembering' => ['price', null, false, 'asc', null],
            'other order, chose ascending, remembering' => ['price', 'asc', true, 'asc', null],
            'other order, chose ascending, not remembering' => ['price', 'asc', false, 'asc', null],
        ];
    }

    /**
     * @return void
     */
    public function testAListingSortOptionKeepsItsOwnDirectionOnAListingWhoseHiddenDefaultItReplaces(): void
    {
        $this->currentOrder = self::LISTING_OPTION;
        $this->hiddenRecordedDefault();
        $this->toolbar->method('getAvailableOrders')->willReturn([self::LISTING_OPTION => 'Best Sellers']);
        $this->policy->method('replacementForDefault')->willReturn(self::LISTING_OPTION);
        $this->memorizer->method('getDirection')->willReturn(null);

        self::assertSame('desc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'asc'));
    }

    /**
     * @return void
     */
    public function testNoCurrentOrderLeavesTheDirectionUntouched(): void
    {
        $this->currentOrder = null;
        $this->policy->method('hidesAnything')->willReturn(false);
        $this->httpContext->expects(self::never())->method('setValue');

        self::assertSame('asc', $this->plugin()->afterGetCurrentDirection($this->toolbar, 'asc'));
    }

    /**
     * @return void
     */
    public function testScriptOptionsAreReturnedUndecodedWhenThePolicyHidesNothing(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('unserialize');
        $serializer->expects(self::never())->method('serialize');
        $this->policy->method('hidesAnything')->willReturn(false);
        $this->policy->expects(self::never())->method('hides');
        $this->toolbar->expects(self::never())->method('getData');
        $json = $this->widgetJson('relevance', 'desc');

        self::assertSame($json, $this->plugin($serializer)->afterGetWidgetOptionsJson($this->toolbar, $json));
    }

    /**
     * @param mixed $recorded Recorded default order
     * @param bool $hidden Whether the policy hides it
     * @return void
     * @dataProvider knownDefaultNotHiddenProvider
     */
    #[DataProvider('knownDefaultNotHiddenProvider')]
    public function testScriptOptionsAreReturnedUndecodedWhenTheKnownDefaultIsNotHidden(
        mixed $recorded,
        bool $hidden
    ): void {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('unserialize');
        $serializer->expects(self::never())->method('serialize');
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn($recorded);
        $this->productListHelper->method('getDefaultSortField')->willReturn(null);
        $this->policy->method('hides')->willReturn($hidden);
        $this->toolbar->expects(self::never())->method('getAvailableOrders');
        $json = $this->widgetJson('relevance', 'desc');

        self::assertSame($json, $this->plugin($serializer)->afterGetWidgetOptionsJson($this->toolbar, $json));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function knownDefaultNotHiddenProvider(): array
    {
        return [
            'default shown' => ['relevance', false],
            'no default known' => [null, true],
        ];
    }

    /**
     * @return void
     */
    public function testScriptOptionsThatAreNotAStringAreReturnedBeforeTheStoreIsAsked(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects(self::never())->method('getStore');
        $plugin = new ToolbarDefaultDirectionPlugin(
            $this->resolver,
            new DefaultDirectionResolver($this->listingSortOptions),
            $storeManager,
            $this->memorizer,
            $this->productListHelper,
            new Json(),
            $this->httpContext
        );

        self::assertNull($plugin->afterGetWidgetOptionsJson($this->toolbar, null));
    }

    /**
     * @param string $json Script options the toolbar printed
     * @return void
     * @dataProvider undecodableScriptOptionsProvider
     */
    #[DataProvider('undecodableScriptOptionsProvider')]
    public function testUndecodableScriptOptionsAreReturnedUntouchedBeforeTheReplacementIsLookedUp(string $json): void
    {
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn('relevance');
        $this->policy->expects(self::once())->method('hides')->with('relevance')->willReturn(true);
        $this->policy->expects(self::never())->method('replacementForDefault');

        self::assertSame($json, $this->plugin()->afterGetWidgetOptionsJson($this->toolbar, $json));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function undecodableScriptOptionsProvider(): array
    {
        return [
            'not JSON' => ['{"productListToolbarForm":'],
            'empty string' => [''],
            'a JSON string' => ['"relevance"'],
            'options under another key' => ['{"toolbar":{"orderDefault":"relevance"}}'],
            'options not an object' => ['{"productListToolbarForm":"relevance"}'],
        ];
    }

    /**
     * The script options carry no default order the plugin can replace, so only the direction changes
     *
     * @param string $json Script options the toolbar printed
     * @param string $expected Script options the plugin should return
     * @return void
     * @dataProvider noUsableDefaultOrderProvider
     */
    #[DataProvider('noUsableDefaultOrderProvider')]
    public function testScriptOptionsWithoutAUsableDefaultOrderGetOnlyTheAscendingDirection(
        string $json,
        string $expected
    ): void {
        $this->currentOrder = 'price';
        $this->hiddenRecordedDefault();
        $this->toolbar->method('getAvailableOrders')->willReturn(self::SHOWN);
        $this->policy->method('replacementForDefault')->with('relevance', self::SHOWN)->willReturn('name');

        self::assertSame($expected, $this->plugin()->afterGetWidgetOptionsJson($this->toolbar, $json));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function noUsableDefaultOrderProvider(): array
    {
        return [
            'no default order' => [
                '{"productListToolbarForm":{"directionDefault":"desc"}}',
                '{"productListToolbarForm":{"directionDefault":"asc"}}',
            ],
            'default order not a string' => [
                '{"productListToolbarForm":{"orderDefault":7}}',
                '{"productListToolbarForm":{"orderDefault":7,"directionDefault":"asc"}}',
            ],
        ];
    }

    /**
     * @return void
     */
    public function testScriptOptionsAreReturnedByteForByteWhenTheListingStillShowsTheHiddenDefault(): void
    {
        $this->hiddenRecordedDefault();
        $this->toolbar->method('getAvailableOrders')->willReturn(['relevance' => 'Relevance']);
        $this->policy->method('replacementForDefault')->willReturn(null);
        $json = $this->widgetJson('relevance', 'desc');

        self::assertSame($json, $this->plugin()->afterGetWidgetOptionsJson($this->toolbar, $json));
    }

    /**
     * @return void
     */
    public function testScriptGetsTheReplacementAscendingAndEveryOtherOptionUnchanged(): void
    {
        $this->hiddenRecordedDefault();
        $this->toolbar->method('getAvailableOrders')->willReturn(self::SHOWN);
        $this->policy->expects(self::atLeastOnce())
            ->method('replacementForDefault')
            ->with('relevance', self::SHOWN)
            ->willReturn('name');
        $this->memorizer->expects(self::never())->method('getDirection');
        $this->httpContext->expects(self::never())->method('setValue');

        $result = $this->plugin()->afterGetWidgetOptionsJson(
            $this->toolbar,
            $this->widgetJson('relevance', 'desc')
        );

        self::assertSame($this->widgetJson('name', 'asc'), $result);
    }

    /**
     * The script's own default order is shown and kept; the listing's recorded default is hidden and replaced, so
     * whatever order is current opens ascending
     *
     * @param string $currentOrder The toolbar's current order
     * @return void
     * @dataProvider currentOrderOffTheListingOptionsProvider
     */
    #[DataProvider('currentOrderOffTheListingOptionsProvider')]
    public function testScriptGetsTheAscendingDirectionWhenOnlyTheRecordedDefaultIsHidden(string $currentOrder): void
    {
        $this->currentOrder = $currentOrder;
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn('relevance');
        $this->policy->method('hides')->willReturnMap([['relevance', true], ['name', false]]);
        $this->toolbar->method('getAvailableOrders')->willReturn(self::SHOWN);
        $this->policy->method('replacementForDefault')->with('relevance', self::SHOWN)->willReturn('name');

        $result = $this->plugin()->afterGetWidgetOptionsJson($this->toolbar, $this->widgetJson('name', 'desc'));

        self::assertSame($this->widgetJson('name', 'asc'), $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function currentOrderOffTheListingOptionsProvider(): array
    {
        return [
            'the replacement' => ['name'],
            'another order' => ['price'],
        ];
    }

    /**
     * @return void
     */
    public function testScriptGetsTheDirectionOfACurrentListingSortOptionAndEveryOtherOptionUnchanged(): void
    {
        $this->currentOrder = self::LISTING_OPTION;
        $this->policy->method('hidesAnything')->willReturn(false);
        $this->memorizer->expects(self::never())->method('getDirection');
        $this->httpContext->expects(self::never())->method('setValue');

        $result = $this->plugin()->afterGetWidgetOptionsJson($this->toolbar, $this->widgetJson('position', 'asc'));

        self::assertSame($this->widgetJson('position', 'desc'), $result);
    }

    /**
     * @return void
     */
    public function testScriptOptionsAlreadyHoldingTheListingSortOptionDirectionAreReturnedByteForByte(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('unserialize')->willReturnCallback(
            static fn (string $json): mixed => json_decode($json, true)
        );
        $serializer->expects(self::never())->method('serialize');
        $this->currentOrder = self::LISTING_OPTION;
        $this->policy->method('hidesAnything')->willReturn(false);
        $json = $this->widgetJson('position', 'desc');

        self::assertSame($json, $this->plugin($serializer)->afterGetWidgetOptionsJson($this->toolbar, $json));
    }

    /**
     * @return void
     */
    public function testScriptGetsTheReplacementOrderAndTheListingSortOptionDirectionWhenBothApply(): void
    {
        $this->currentOrder = self::LISTING_OPTION;
        $this->hiddenRecordedDefault();
        $this->toolbar->method('getAvailableOrders')->willReturn(self::SHOWN);
        $this->policy->method('replacementForDefault')->with('relevance', self::SHOWN)->willReturn('name');

        $result = $this->plugin()->afterGetWidgetOptionsJson(
            $this->toolbar,
            $this->widgetJson('relevance', 'asc')
        );

        self::assertSame($this->widgetJson('name', 'desc'), $result);
    }

    /**
     * Mark the recorded default order "relevance" as hidden by the policy
     *
     * @return void
     */
    private function hiddenRecordedDefault(): void
    {
        $this->policy->method('hidesAnything')->willReturn(true);
        $this->toolbar->method('getData')->willReturn('relevance');
        $this->policy->method('hides')->with('relevance')->willReturn(true);
    }

    /**
     * Let the listing's shown options replace the hidden default "relevance" with "name"
     *
     * @return void
     */
    private function replacedByName(): void
    {
        $this->toolbar->method('getAvailableOrders')->willReturn(self::SHOWN);
        $this->policy->expects(self::once())
            ->method('replacementForDefault')
            ->with('relevance', self::SHOWN)
            ->willReturn('name');
    }

    /**
     * Build the script options JSON the way the toolbar prints it
     *
     * @param string $orderDefault Default order code
     * @param string $directionDefault Default direction
     * @return string
     */
    private function widgetJson(string $orderDefault, string $directionDefault): string
    {
        $encoded = json_encode([
            'productListToolbarForm' => [
                'mode' => 'product_list_mode',
                'direction' => 'product_list_dir',
                'order' => 'product_list_order',
                'limit' => 'product_list_limit',
                'modeDefault' => 'grid',
                'directionDefault' => $directionDefault,
                'orderDefault' => $orderDefault,
                'limitDefault' => 12,
                'url' => 'https://example.test/catalogsearch/result/?q=caf%C3%A9&label=Café',
                'formKey' => 'aBc123',
                'post' => false,
            ],
        ]);
        self::assertIsString($encoded);

        return $encoded;
    }

    /**
     * Build the plugin under test with the real direction resolver
     *
     * @param SerializerInterface|null $serializer Serializer double; the real JSON serializer when null
     * @return ToolbarDefaultDirectionPlugin
     */
    private function plugin(?SerializerInterface $serializer = null): ToolbarDefaultDirectionPlugin
    {
        return new ToolbarDefaultDirectionPlugin(
            $this->resolver,
            new DefaultDirectionResolver($this->listingSortOptions),
            $this->storeManager,
            $this->memorizer,
            $this->productListHelper,
            $serializer ?? new Json(),
            $this->httpContext
        );
    }
}
