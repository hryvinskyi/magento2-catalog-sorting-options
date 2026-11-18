<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Integration;

use Hryvinskyi\CatalogSortingOptions\Api\ListingSortOptionsInterface;
use Hryvinskyi\CatalogSortingOptions\Model\ConfigFlagListingSortOption;
use Hryvinskyi\CatalogSortingOptions\Model\DefaultDirectionResolver;
use Hryvinskyi\CatalogSortingOptions\Model\ListingSortOptions;
use Hryvinskyi\CatalogSortingOptions\Model\Source\ListingSortOptionsSource;
use Hryvinskyi\CatalogSortingOptions\Model\SortOptionCatalogue;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\CatalogConfigListingSortOptionsPlugin;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\CategorySortBySourceListingSortOptionsPlugin;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\ListSortSourceListingSortOptionsPlugin;
use Hryvinskyi\CatalogSortingOptions\Plugin\Catalog\ToolbarDefaultDirectionPlugin;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager as TestObjectManager;

/**
 * Registers one listing sort option, offered where its own Yes/No setting is on, in the real container
 *
 * Its code, label and setting path are those of {@see TestListingSortOption}.
 *
 * The option is built by the container from the generic configured class, as a module's virtual type would be, and
 * replaces the shared list of listing sort options for the rest of the test. Every shared service built on that
 * list is dropped first, so the next one asked for is built on the test's list. Run each test that uses it with
 * application isolation, so the replaced list does not outlive the test.
 */
trait RegistersListingSortOption
{
    /**
     * Register the test listing sort option as the only listing sort option, opening descending
     *
     * @return void
     */
    private function registerListingSortOption(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        if (!$objectManager instanceof TestObjectManager) {
            throw new \LogicException('Integration tests need the test framework object manager.');
        }

        $option = $objectManager->create(ConfigFlagListingSortOption::class, [
            'code' => TestListingSortOption::CODE,
            'label' => TestListingSortOption::LABEL,
            'enabledPath' => TestListingSortOption::ENABLED_PATH,
        ]);
        $options = $objectManager->create(ListingSortOptions::class, ['options' => ['test' => $option]]);

        foreach ([
            DefaultDirectionResolver::class,
            ListingSortOptionsSource::class,
            SortOptionCatalogue::class,
            CatalogConfigListingSortOptionsPlugin::class,
            CategorySortBySourceListingSortOptionsPlugin::class,
            ListSortSourceListingSortOptionsPlugin::class,
            ToolbarDefaultDirectionPlugin::class,
        ] as $dependent) {
            $objectManager->removeSharedInstance($dependent);
        }
        $objectManager->addSharedInstance($options, ListingSortOptionsInterface::class, true);
    }
}
