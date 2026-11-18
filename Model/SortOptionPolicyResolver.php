<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Model;

use Hryvinskyi\CatalogSortingOptions\Api\SortOptionOverridesParserInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterfaceFactory;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Exception\InvalidSortOptionOverridesException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds a store's sort option policy from the stored setting, once per store and request
 *
 * It runs on the storefront hot path, so it asks for nothing beyond the config reader, the JSON serializer, the
 * parser and the policy factory. A store without a stored value gets a shared policy without overrides and the
 * value is not decoded. A value that cannot be read (bad JSON, not a list of rows, a row that breaks the rules)
 * is logged once and also yields a policy without overrides: stored settings never fail a storefront page.
 */
class SortOptionPolicyResolver implements SortOptionPolicyResolverInterface
{
    /**
     * @var array<int, SortOptionPolicyInterface> Policies built so far, by store id
     */
    private array $policies = [];

    /**
     * @var SortOptionPolicyInterface|null Policy without overrides, built when first needed
     */
    private ?SortOptionPolicyInterface $emptyPolicy = null;

    /**
     * @param ScopeConfigInterface $scopeConfig Reads the stored setting
     * @param SerializerInterface $serializer Decodes the stored setting
     * @param SortOptionOverridesParserInterface $parser Turns decoded rows into overrides
     * @param SortOptionPolicyInterfaceFactory $policyFactory Creates the policy
     * @param LoggerInterface $logger Receives the warning for an unreadable setting
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer,
        private readonly SortOptionOverridesParserInterface $parser,
        private readonly SortOptionPolicyInterfaceFactory $policyFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forStore(int $storeId): SortOptionPolicyInterface
    {
        return $this->policies[$storeId] ??= $this->build($storeId);
    }

    /**
     * Build the policy of a store from its stored setting
     *
     * @param int $storeId Store view id
     * @return SortOptionPolicyInterface
     */
    private function build(int $storeId): SortOptionPolicyInterface
    {
        $stored = $this->scopeConfig->getValue(self::XML_PATH_OVERRIDES, ScopeInterface::SCOPE_STORE, $storeId);
        if ($stored === null || (is_string($stored) && trim($stored) === '')) {
            return $this->emptyPolicy();
        }

        try {
            return $this->policyFactory->create(['overrides' => $this->parser->parse($this->decode($stored))]);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning(sprintf(
                'Ignoring unreadable sort option settings for store %d: %s',
                $storeId,
                $exception->getMessage()
            ));

            return $this->emptyPolicy();
        }
    }

    /**
     * Decode the stored setting into rows
     *
     * @param mixed $stored The stored value
     * @return array<array-key,mixed> Row id => row
     * @throws \InvalidArgumentException When the value is not JSON; {@see InvalidSortOptionOverridesException}
     *     when it is JSON but not an array
     */
    private function decode(mixed $stored): array
    {
        $rows = is_string($stored) ? $this->serializer->unserialize($stored) : $stored;
        if (!is_array($rows)) {
            throw new InvalidSortOptionOverridesException('The stored value is not a list of rows.');
        }

        return $rows;
    }

    /**
     * Get the policy without overrides, shared by every store that has none
     *
     * @return SortOptionPolicyInterface
     */
    private function emptyPolicy(): SortOptionPolicyInterface
    {
        return $this->emptyPolicy ??= $this->policyFactory->create(['overrides' => []]);
    }
}
