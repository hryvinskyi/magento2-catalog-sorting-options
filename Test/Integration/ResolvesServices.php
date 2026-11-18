<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;

/**
 * Gives an integration test typed access to the services of the real container
 */
trait ResolvesServices
{
    /**
     * Get a shared service from the real container, checked to be of the requested type
     *
     * @template T of object
     * @param class-string<T> $type Interface or class to resolve
     * @return T
     */
    private function service(string $type): object
    {
        $service = Bootstrap::getObjectManager()->get($type);
        if (!$service instanceof $type) {
            throw new \LogicException(sprintf('The container returned a service that is not a %s.', $type));
        }

        return $service;
    }
}
