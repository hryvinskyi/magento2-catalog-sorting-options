<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model\Config\Backend;

use Hryvinskyi\CatalogSortingOptions\Api\Data\SortOptionOverrideInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionOverridesParserInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Exception\InvalidSortOptionOverridesException;
use Hryvinskyi\CatalogSortingOptions\Model\Config\Backend\SortOptionOverrides;
use Hryvinskyi\CatalogSortingOptions\Model\Data\SortOptionOverride;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionPolicy;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The setting refuses rows without an option, with a bad place in list, or naming one option twice, and
 * otherwise stores the posted rows as JSON without the blank-table marker.
 */
class SortOptionOverridesTest extends TestCase
{
    /**
     * @var SortOptionOverridesParserInterface&MockObject
     */
    private $parser;

    /**
     * @var SortOptionPolicyInterfaceFactory&MockObject
     */
    private $policyFactory;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->serializer = new Json();
        $this->parser = $this->createMock(SortOptionOverridesParserInterface::class);
        $this->parser->method('parse')->willReturnCallback($this->parseRows(...));
        $this->policyFactory = $this->createMock(SortOptionPolicyInterfaceFactory::class);
        $this->policyFactory->method('create')->willReturnCallback(
            static fn (array $arguments): SortOptionPolicy => new SortOptionPolicy(
                array_values(array_filter(
                    (array) $arguments['overrides'],
                    static fn (mixed $override): bool => $override instanceof SortOptionOverrideInterface
                ))
            )
        );
    }

    /**
     * @return void
     */
    public function testRefusesAnOptionListedTwiceAndNamesIt(): void
    {
        $model = $this->model([
            '_1' => ['code' => 'price', 'shown' => '0', 'label' => '', 'position' => ''],
            '_2' => ['code' => 'price', 'shown' => '1', 'label' => 'Cost', 'position' => '2'],
            '__empty' => '',
        ]);

        try {
            $model->beforeSave();
            self::fail('A sort option listed twice was accepted.');
        } catch (LocalizedException $exception) {
            self::assertSame(
                'Sort option "price" is listed more than once. Keep one row per option.',
                $exception->getMessage()
            );
        }
    }

    /**
     * @return void
     */
    public function testRefusesARowWithoutAnOption(): void
    {
        $model = $this->model([
            '_1' => ['code' => '  ', 'shown' => '1', 'label' => 'Cost', 'position' => ''],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Every sort option row needs an option, and Place in List must be blank or a whole number from 1.'
        );

        $model->beforeSave();
    }

    /**
     * @return void
     */
    public function testRefusesAPlaceInListThatIsNotAWholeNumberFromOne(): void
    {
        $model = $this->model([
            '_1' => ['code' => 'price', 'shown' => '1', 'label' => '', 'position' => 'first'],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Every sort option row needs an option, and Place in List must be blank or a whole number from 1.'
        );

        $model->beforeSave();
    }

    /**
     * @return void
     */
    public function testTheBlankTableMarkerAloneSavesAsAnEmptyList(): void
    {
        $model = $this->model(['__empty' => '']);

        $model->beforeSave();

        self::assertSame('[]', $model->getValue());
    }

    /**
     * @return void
     */
    public function testValidRowsAreStoredAsJsonWithoutTheBlankTableMarker(): void
    {
        $rows = [
            '_1' => ['code' => 'position', 'shown' => '0', 'label' => '', 'position' => ''],
            '_2' => ['code' => 'price', 'shown' => '1', 'label' => 'Cost', 'position' => '1'],
        ];
        $model = $this->model($rows + ['__empty' => '']);

        $model->beforeSave();

        $value = $model->getData('value');
        self::assertIsString($value);
        self::assertSame($rows, $this->serializer->unserialize($value));
    }

    /**
     * @return void
     */
    public function testAValueThatIsNotARowListIsLeftToTheParentToHandle(): void
    {
        $this->parser->expects(self::never())->method('parse');
        $model = $this->model('');

        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    /**
     * Build the backend model holding a posted value
     *
     * @param mixed $value Posted value
     * @return SortOptionOverrides
     */
    private function model(mixed $value): SortOptionOverrides
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        $model = new SortOptionOverrides(
            $context,
            $this->createMock(Registry::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(TypeListInterface::class),
            $this->parser,
            $this->policyFactory,
            null,
            null,
            [],
            $this->serializer
        );
        $model->setData('value', $value);

        return $model;
    }

    /**
     * Stand-in for the parser: reads rows the way the real one is specified to
     *
     * @param array<array-key,mixed> $rows Row id => row
     * @return list<SortOptionOverrideInterface>
     * @throws InvalidSortOptionOverridesException
     */
    private function parseRows(array $rows): array
    {
        $overrides = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['code'] ?? null) || trim($row['code']) === '') {
                throw new InvalidSortOptionOverridesException('A row needs an option code.');
            }
            $position = is_string($row['position'] ?? null) ? $row['position'] : '';
            if ($position !== '' && (!ctype_digit($position) || (int) $position < 1)) {
                throw new InvalidSortOptionOverridesException('Place in list must be a whole number from 1.');
            }
            $overrides[] = new SortOptionOverride(
                $row['code'],
                ($row['shown'] ?? '1') !== '0',
                is_string($row['label'] ?? null) ? $row['label'] : null,
                $position === '' ? null : (int) $position
            );
        }

        return $overrides;
    }
}
