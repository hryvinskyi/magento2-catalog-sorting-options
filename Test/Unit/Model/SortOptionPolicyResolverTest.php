<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;
use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Model\Data\SortOptionOverride;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionOverridesParser;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionPolicy;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionPolicyResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The resolver reads a store's stored setting once per request, hands back a policy that applies it, and
 * answers an unreadable setting with a policy without overrides and one warning instead of an error.
 */
class SortOptionPolicyResolverTest extends TestCase
{
    private const OPTIONS = ['position' => 'Position', 'name' => 'Name', 'price' => 'Price'];

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var SerializerInterface&MockObject
     */
    private $serializer;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var SortOptionPolicyInterfaceFactory&MockObject
     */
    private $policyFactory;

    /**
     * @var SortOptionOverrideInterfaceFactory&MockObject
     */
    private $overrideFactory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->policyFactory = $this->createMock(SortOptionPolicyInterfaceFactory::class);
        $this->policyFactory->method('create')->willReturnCallback(self::buildPolicy(...));
        $this->overrideFactory = $this->createMock(SortOptionOverrideInterfaceFactory::class);
        $this->overrideFactory->method('create')->willReturnCallback(self::buildOverride(...));
    }

    /**
     * @return void
     */
    public function testReadsTheSettingAtStoreScopeForTheAskedStore(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with(SortOptionPolicyResolverInterface::XML_PATH_OVERRIDES, ScopeInterface::SCOPE_STORE, 7)
            ->willReturn(null);

        $this->resolver(true)->forStore(7);
    }

    /**
     * @return void
     */
    public function testAppliesTheStoredOverrides(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(
            '{"a":{"code":"position","shown":"0"},"b":{"code":"price","shown":"1","label":"Cost","position":"1"}}'
        );

        $applied = $this->resolver(true)->forStore(1)->apply(self::OPTIONS);

        self::assertSame(['price' => 'Cost', 'name' => 'Name'], $applied);
    }

    /**
     * @param mixed $stored Stored value that means "nothing configured"
     * @return void
     */
    #[DataProvider('blankValues')]
    public function testABlankSettingGivesNoOverridesAndIsNeverDecodedOrLogged(mixed $stored): void
    {
        $this->scopeConfig->method('getValue')->willReturn($stored);
        $this->serializer->expects(self::never())->method('unserialize');
        $this->logger->expects(self::never())->method('warning');

        $applied = $this->resolver(false)->forStore(1)->apply(self::OPTIONS);

        self::assertSame(self::OPTIONS, $applied);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function blankValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'blank string' => ["  \n"],
        ];
    }

    /**
     * @return void
     */
    public function testTheSettingIsReadAndDecodedOncePerStore(): void
    {
        $this->scopeConfig->expects(self::exactly(2))
            ->method('getValue')
            ->willReturn('{"a":{"code":"price","shown":"0"}}');

        $resolver = $this->resolver(true);
        $first = $resolver->forStore(1);

        self::assertSame($first, $resolver->forStore(1));
        self::assertNotSame($first, $resolver->forStore(2));
        self::assertSame($resolver->forStore(2), $resolver->forStore(2));
    }

    /**
     * @return void
     */
    public function testStoresWithoutASettingShareOnePolicy(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $resolver = $this->resolver(false);

        self::assertSame($resolver->forStore(1), $resolver->forStore(2));
    }

    /**
     * @return void
     */
    public function testAnUnreadableSettingIsAlsoRememberedSoItIsLoggedOnce(): void
    {
        $this->scopeConfig->expects(self::once())->method('getValue')->willReturn('not json');
        $this->logger->expects(self::once())->method('warning');

        $resolver = $this->resolver(true);
        $resolver->forStore(3);
        $resolver->forStore(3);
    }

    /**
     * @param mixed $stored Stored value that cannot be read as overrides
     * @return void
     */
    #[DataProvider('unreadableValues')]
    public function testAnUnreadableSettingGivesNoOverridesAndOneWarningNamingTheStore(mixed $stored): void
    {
        $this->scopeConfig->method('getValue')->willReturn($stored);
        $this->logger->expects(self::once())
            ->method('warning')
            ->with(self::stringStartsWith('Ignoring unreadable sort option settings for store 42: '));

        $applied = $this->resolver(true)->forStore(42)->apply(self::OPTIONS);

        self::assertSame(self::OPTIONS, $applied);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unreadableValues(): array
    {
        return [
            'bad json' => ['{"a":'],
            'json scalar' => ['5'],
            'json string' => ['"price"'],
            'row is not an array' => ['{"a":"price"}'],
            'row without code' => ['{"a":{"shown":"0"}}'],
            'bad place' => ['{"a":{"code":"price","position":"0"}}'],
            'repeated code' => ['{"a":{"code":"price"},"b":{"code":"price","shown":"0"}}'],
            'stored value is not text' => [['a' => 'b']],
        ];
    }

    /**
     * Build a real policy from the array the generated factory's create() receives
     *
     * @param array{overrides: list<SortOptionOverrideInterface>} $data
     *     Arguments keyed by constructor parameter name
     * @return SortOptionPolicy
     */
    private static function buildPolicy(array $data): SortOptionPolicy
    {
        return new SortOptionPolicy(...$data);
    }

    /**
     * Build a real override from the array the generated factory's create() receives
     *
     * @param array{code: string, shown: bool, label: string|null, position: int|null} $data Arguments keyed by
     *     constructor parameter name
     * @return SortOptionOverride
     */
    private static function buildOverride(array $data): SortOptionOverride
    {
        return new SortOptionOverride(...$data);
    }

    /**
     * Build the resolver under test around the real parser and a real JSON decoder
     *
     * @param bool $decodes Whether the serializer should really decode; false leaves it a strict mock
     * @return SortOptionPolicyResolver
     */
    private function resolver(bool $decodes): SortOptionPolicyResolver
    {
        if ($decodes) {
            $this->serializer->method('unserialize')->willReturnCallback(self::decode(...));
        }

        return new SortOptionPolicyResolver(
            $this->scopeConfig,
            $this->serializer,
            new SortOptionOverridesParser($this->overrideFactory),
            $this->policyFactory,
            $this->logger
        );
    }

    /**
     * Decode JSON the way the framework serializer does
     *
     * @param string $value JSON text
     * @return mixed
     * @throws \InvalidArgumentException When the text is not valid JSON
     */
    private static function decode(string $value): mixed
    {
        $decoded = json_decode($value, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Unable to unserialize value. Error: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
