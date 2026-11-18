<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Model\ConfigFlagListingSortOption;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The option is offered where its Yes/No setting is on at store scope, opens descending unless told otherwise,
 * translates its label each time it is read, and refuses a blank code, label or setting path or an unknown
 * direction when it is built.
 */
class ConfigFlagListingSortOptionTest extends TestCase
{
    /**
     * Config path of the test option's Yes/No setting
     */
    private const ENABLED_PATH = 'catalog_sorting/bestsellers/enabled';

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var RendererInterface The phrase renderer in place before the test
     */
    private RendererInterface $originalRenderer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->originalRenderer = Phrase::getRenderer();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Phrase::setRenderer($this->originalRenderer);
    }

    /**
     * @return void
     */
    public function testGivesItsCodeAndOpensDescendingByDefault(): void
    {
        $option = $this->option();

        self::assertSame('bestsellers', $option->getCode());
        self::assertSame(SortDirection::Descending, $option->getDefaultDirection());
    }

    /**
     * @param string $configured Direction as configured
     * @param SortDirection $expected Direction the option opens in
     * @return void
     * @dataProvider directionProvider
     */
    #[DataProvider('directionProvider')]
    public function testOpensInTheConfiguredDirectionWhateverItsCase(string $configured, SortDirection $expected): void
    {
        self::assertSame($expected, $this->option(defaultDirection: $configured)->getDefaultDirection());
    }

    /**
     * @return array<string, array{string, SortDirection}>
     */
    public static function directionProvider(): array
    {
        return [
            'ascending' => ['asc', SortDirection::Ascending],
            'ascending in capitals, padded' => [' ASC ', SortDirection::Ascending],
            'descending' => ['desc', SortDirection::Descending],
        ];
    }

    /**
     * @return void
     */
    public function testTheLabelIsTranslatedWhenRead(): void
    {
        $option = $this->option();
        $renderer = $this->createMock(RendererInterface::class);
        $renderer->method('render')->willReturnCallback(
            static fn (array $source): string => $source === ['Best Sellers'] ? 'Meilleures ventes' : 'untranslated'
        );
        Phrase::setRenderer($renderer);

        self::assertSame('Meilleures ventes', $option->getLabel());
    }

    /**
     * @param bool $flag Whether the setting is on in the store
     * @return void
     * @dataProvider flagProvider
     */
    #[DataProvider('flagProvider')]
    public function testIsOfferedWhereItsSettingIsOnAtStoreScope(bool $flag): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, 4)
            ->willReturn($flag);

        self::assertSame($flag, $this->option()->isOffered(4));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function flagProvider(): array
    {
        return [
            'on' => [true],
            'off' => [false],
        ];
    }

    /**
     * @param string $code Sort code
     * @param string $label Label
     * @param string $enabledPath Setting path
     * @param string $message Expected message
     * @return void
     * @dataProvider blankArgumentProvider
     */
    #[DataProvider('blankArgumentProvider')]
    public function testABlankArgumentIsRefused(string $code, string $label, string $enabledPath, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->option($code, $label, $enabledPath);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function blankArgumentProvider(): array
    {
        return [
            'code' => [' ', 'Best Sellers', self::ENABLED_PATH, 'Listing sort option " " needs a non-blank code.'],
            'label' => [
                'bestsellers',
                '',
                self::ENABLED_PATH,
                'Listing sort option "bestsellers" needs a non-blank label.',
            ],
            'path' => [
                'bestsellers',
                'Best Sellers',
                '  ',
                'Listing sort option "bestsellers" needs a non-blank enabledPath.',
            ],
        ];
    }

    /**
     * @return void
     */
    public function testAnUnknownDirectionIsRefusedAndNamed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listing sort option "bestsellers" has default direction "down"');

        $this->option(defaultDirection: 'down');
    }

    /**
     * Build the option under test
     *
     * @param string $code Sort code
     * @param string $label Label
     * @param string $enabledPath Setting path
     * @param string $defaultDirection Default direction
     * @return ConfigFlagListingSortOption
     */
    private function option(
        string $code = 'bestsellers',
        string $label = 'Best Sellers',
        string $enabledPath = self::ENABLED_PATH,
        string $defaultDirection = 'desc'
    ): ConfigFlagListingSortOption {
        return new ConfigFlagListingSortOption($this->scopeConfig, $code, $label, $enabledPath, $defaultDirection);
    }
}
