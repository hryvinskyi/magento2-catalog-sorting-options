<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Model;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionSourceInterface;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionCatalogue;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The catalogue lists every source's options in pool order, keeps the first label offered for a code, and refuses
 * a pool item that is not a source, naming it.
 */
class SortOptionCatalogueTest extends TestCase
{
    /**
     * @return void
     */
    public function testWithoutSourcesOffersNothing(): void
    {
        self::assertSame([], (new SortOptionCatalogue())->getSortOptions());
    }

    /**
     * @return void
     */
    public function testListsTheOptionsOfEverySourceInPoolOrder(): void
    {
        $catalogue = new SortOptionCatalogue([
            'first' => $this->source(['position' => 'Position', 'name' => 'Name']),
            'second' => $this->source(['relevance' => 'Relevance']),
        ]);

        self::assertSame(
            ['position' => 'Position', 'name' => 'Name', 'relevance' => 'Relevance'],
            $catalogue->getSortOptions()
        );
    }

    /**
     * @return void
     */
    public function testTheFirstLabelOfACodeWinsAndKeepsItsPlace(): void
    {
        $catalogue = new SortOptionCatalogue([
            'first' => $this->source(['name' => 'Name', 'price' => 'Price']),
            'second' => $this->source(['name' => 'Title', 'rating' => 'Rating']),
        ]);

        self::assertSame(
            ['name' => 'Name', 'price' => 'Price', 'rating' => 'Rating'],
            $catalogue->getSortOptions()
        );
    }

    /**
     * @return void
     */
    public function testRefusesAPoolItemThatIsNotASourceNamingIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort option source "broken" must implement');

        new SortOptionCatalogue([
            'ok' => $this->source(['name' => 'Name']),
            'broken' => new \stdClass(),
        ]);
    }

    /**
     * Build a source offering the given options
     *
     * @param array<string,string> $options Option code => label
     * @return SortOptionSourceInterface&MockObject
     */
    private function source(array $options): SortOptionSourceInterface
    {
        $source = $this->createMock(SortOptionSourceInterface::class);
        $source->method('getSortOptions')->willReturn($options);

        return $source;
    }
}
