<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Plugin\Catalog;

use Hryvinskyi\CatalogSorting\Api\Data\SortDirection;
use Hryvinskyi\CatalogSortingOptions\Api\DefaultDirectionResolverInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyInterface;
use Hryvinskyi\CatalogSortingOptions\Api\SortOptionPolicyResolverInterface;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Helper\Product\ProductList;
use Magento\Catalog\Model\Product\ProductList\Toolbar as ToolbarModel;
use Magento\Catalog\Model\Product\ProductList\ToolbarMemorizer;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Gives a listing the default direction its current order calls for, and a hidden default order its replacement
 *
 * The direction rules belong to the default direction resolver; this plugin gathers what the resolver needs from
 * the toolbar and hands the answer to the toolbar, its script and the page cache. When the resolver has no answer,
 * the toolbar's own direction and HTTP context stand untouched.
 *
 * A direction the shopper chose, in the URL or remembered by the catalog session, always wins over the answer.
 *
 * Page cache: when the resolver answers and the store remembers the shopper's toolbar choices, the direction is
 * written to the HTTP context against the resolver's answer as the default, also when the shopper chose it. A
 * shopper who chose nothing then sees the default and adds nothing to the cache key, so the page shares one entry;
 * a shopper who remembered the other direction gets an entry of their own, never the one of a shopper who sees the
 * default. Recording against the toolbar's own default instead would split the cache for every untouched page whose
 * default differs from it, and let a remembered direction equal to it share the entry of a page in the other order.
 *
 * The toolbar script receives the same defaults, so picking the effective default order or direction removes the
 * parameter from the URL instead of adding it: one page, one URL, one cache entry.
 *
 * The toolbar keeps its configured default order in a protected property, so the order a caller requests through
 * setDefaultOrder() is recorded in the block's own data. The record lives and dies with that block.
 *
 * Lookups run cheapest first: the listing's default order and the toolbar's options are read only when the store's
 * policy hides something and, for the options, only when it hides that default. The toolbar script options are
 * decoded only when a direction or a replaced default order may change them, and are returned byte for byte when
 * nothing changes.
 */
class ToolbarDefaultDirectionPlugin
{
    /**
     * Block data key holding the default order a caller requested from the toolbar
     */
    public const DEFAULT_ORDER_DATA_KEY = 'sort_options_default_order';

    /**
     * Directions that count as the shopper's own choice, compared in lower case
     */
    private const SHOPPER_DIRECTIONS = ['asc', 'desc'];

    /**
     * Top-level key of the toolbar script options
     */
    private const WIDGET_OPTIONS_KEY = 'productListToolbarForm';

    /**
     * @param SortOptionPolicyResolverInterface $policyResolver Gives the policy configured for a store
     * @param DefaultDirectionResolverInterface $directionResolver Decides the default direction of an order
     * @param StoreManagerInterface $storeManager Gives the store the listing is served for
     * @param ToolbarMemorizer $toolbarMemorizer Gives the direction the shopper chose, if any
     * @param ProductList $productListHelper Gives the configured default order when no caller requested one
     * @param SerializerInterface $serializer Decodes and encodes the toolbar script options as JSON
     * @param HttpContext $httpContext Page cache vary values; gets the direction against the effective default
     */
    public function __construct(
        private readonly SortOptionPolicyResolverInterface $policyResolver,
        private readonly DefaultDirectionResolverInterface $directionResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly ToolbarMemorizer $toolbarMemorizer,
        private readonly ProductList $productListHelper,
        private readonly SerializerInterface $serializer,
        private readonly HttpContext $httpContext
    ) {
    }

    /**
     * Record the default order a caller requested, which the toolbar keeps out of reach of a plugin
     *
     * The toolbar ignores a requested order it does not offer; the record keeps it anyway. Such an order is never
     * shown, so it only changes the direction when the policy also hides it.
     *
     * @param Toolbar $subject Toolbar block
     * @param mixed $result The toolbar itself
     * @param mixed $field Requested default order code
     * @return mixed
     */
    public function afterSetDefaultOrder(Toolbar $subject, mixed $result, mixed $field): mixed
    {
        if ($this->nonBlank($field) !== null) {
            $subject->setData(self::DEFAULT_ORDER_DATA_KEY, $field);
        }

        return $result;
    }

    /**
     * Use the effective default direction of the current order unless the shopper chose a direction
     *
     * Without an answer from the resolver the toolbar's direction is returned untouched and nothing is written.
     * With one, the shopper's chosen direction is kept, else the answer is used; when the store remembers toolbar
     * choices, that direction is written to the HTTP context with the answer as its default.
     *
     * @param Toolbar $subject Toolbar block
     * @param mixed $result Direction the toolbar resolved
     * @return mixed
     */
    public function afterGetCurrentDirection(Toolbar $subject, mixed $result): mixed
    {
        $policy = $this->currentPolicy();
        $effectiveDefault = $this->effectiveDefaultDirection(
            $subject,
            $policy,
            $this->hiddenDefaultOrder($subject, $policy)
        );
        if ($effectiveDefault === null) {
            return $result;
        }

        $direction = $this->shopperChoseDirection() ? $result : $effectiveDefault->value;
        if ($this->toolbarMemorizer->isMemorizingAllowed()) {
            $this->httpContext->setValue(ToolbarModel::DIRECTION_PARAM_NAME, $direction, $effectiveDefault->value);
        }

        return $direction;
    }

    /**
     * Give the toolbar script the effective default order and the effective default direction of the current order
     *
     * The script drops the order and direction parameters from the URL when they equal its defaults. The default
     * order is replaced as the toolbar's own: when the policy hides it and the listing's shown options replace it.
     * When nothing changes, or the options are not the JSON object the toolbar prints, the result is returned byte
     * for byte.
     *
     * @param Toolbar $subject Toolbar block
     * @param mixed $result Toolbar script options as JSON
     * @return mixed
     */
    public function afterGetWidgetOptionsJson(Toolbar $subject, mixed $result): mixed
    {
        if (!is_string($result)) {
            return $result;
        }

        $policy = $this->currentPolicy();
        $hiddenDefault = $this->hiddenDefaultOrder($subject, $policy);
        $direction = $hiddenDefault === null ? $this->effectiveDefaultDirection($subject, $policy, null) : null;
        if ($hiddenDefault === null && $direction === null) {
            return $result;
        }

        $document = $this->decode($result);
        $options = $document[self::WIDGET_OPTIONS_KEY] ?? null;
        if (!is_array($options)) {
            return $result;
        }

        $updated = $options;
        if ($hiddenDefault !== null) {
            $updated = $this->withReplacedDefaultOrder($updated, $subject, $policy);
            $direction = $this->effectiveDefaultDirection($subject, $policy, $hiddenDefault);
        }
        if ($direction !== null) {
            $updated['directionDefault'] = $direction->value;
        }

        if ($updated === $options) {
            return $result;
        }

        $document[self::WIDGET_OPTIONS_KEY] = $updated;

        return $this->serializer->serialize($document);
    }

    /**
     * Get the sort option policy of the store being served
     *
     * @return SortOptionPolicyInterface
     */
    private function currentPolicy(): SortOptionPolicyInterface
    {
        return $this->policyResolver->forStore((int)$this->storeManager->getStore()->getId());
    }

    /**
     * Ask the resolver for the default direction of the toolbar's current order
     *
     * @param Toolbar $subject Toolbar block
     * @param SortOptionPolicyInterface $policy The store's policy
     * @param string|null $hiddenDefault The listing's default order when the policy hides it
     * @return SortDirection|null Null when the toolbar has no current order or no rule applies
     */
    private function effectiveDefaultDirection(
        Toolbar $subject,
        SortOptionPolicyInterface $policy,
        ?string $hiddenDefault
    ): ?SortDirection {
        $order = $this->nonBlank($subject->getCurrentOrder());
        if ($order === null) {
            return null;
        }

        return $this->directionResolver->forOrder(
            $order,
            $policy,
            $hiddenDefault,
            $hiddenDefault === null ? [] : $this->availableOrders($subject)
        );
    }

    /**
     * Get the listing's default order when the policy hides it
     *
     * Only a hidden default can be replaced, so a default the policy shows is not worth passing on, and a policy
     * that hides nothing is answered before the default order is looked up.
     *
     * @param Toolbar $subject Toolbar block
     * @param SortOptionPolicyInterface $policy The store's policy
     * @return string|null Null when no default order is known or the policy shows it
     */
    private function hiddenDefaultOrder(Toolbar $subject, SortOptionPolicyInterface $policy): ?string
    {
        if (!$policy->hidesAnything()) {
            return null;
        }

        $defaultOrder = $this->defaultOrder($subject);

        return $defaultOrder !== null && $policy->hides($defaultOrder) ? $defaultOrder : null;
    }

    /**
     * Replace the script's default order when the policy hides it and the listing's shown options replace it
     *
     * @param array<mixed> $options Toolbar script options
     * @param Toolbar $subject Toolbar block
     * @param SortOptionPolicyInterface $policy The store's policy
     * @return array<mixed> The options, with the replacement as default order when there is one
     */
    private function withReplacedDefaultOrder(
        array $options,
        Toolbar $subject,
        SortOptionPolicyInterface $policy
    ): array {
        $defaultOrder = $this->nonBlank($options['orderDefault'] ?? null);
        if ($defaultOrder === null || !$policy->hides($defaultOrder)) {
            return $options;
        }

        $replacement = $policy->replacementForDefault($defaultOrder, $this->availableOrders($subject));
        if ($replacement !== null) {
            $options['orderDefault'] = $replacement;
        }

        return $options;
    }

    /**
     * Get the listing's default order: the one a caller requested, else the configured one
     *
     * @param Toolbar $subject Toolbar block
     * @return string|null Null when neither is a non-blank string
     */
    private function defaultOrder(Toolbar $subject): ?string
    {
        return $this->nonBlank($subject->getData(self::DEFAULT_ORDER_DATA_KEY))
            ?? $this->nonBlank($this->productListHelper->getDefaultSortField());
    }

    /**
     * Get the options the toolbar shows
     *
     * @param Toolbar $subject Toolbar block
     * @return array<array-key,mixed> Code => label
     */
    private function availableOrders(Toolbar $subject): array
    {
        return $subject->getAvailableOrders();
    }

    /**
     * Whether the shopper chose a direction, in the URL or remembered by the catalog session
     *
     * @return bool
     */
    private function shopperChoseDirection(): bool
    {
        $direction = $this->toolbarMemorizer->getDirection();

        return is_string($direction) && in_array(strtolower($direction), self::SHOPPER_DIRECTIONS, true);
    }

    /**
     * Decode the toolbar script options
     *
     * @param string $json Toolbar script options as JSON
     * @return array<mixed> Empty when the value is not a JSON object or array
     */
    private function decode(string $json): array
    {
        try {
            $decoded = $this->serializer->unserialize($json);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Narrow a value to a non-blank string
     *
     * @param mixed $value Any value
     * @return string|null The value unchanged when it is a string with a non-space character, else null
     */
    private function nonBlank(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
