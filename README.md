# Magento 2 Catalog Sorting Options

Per-store control of the storefront Sort By list: hide, rename and reorder its options, and offer the listing sort options other modules register (Best Sellers, Most Viewed and the like), each opening in its own direction.

## What it does for a store

- **Hide, rename and move sort options** per store view, on category pages and search results. Any option can be overridden: Position, Name, Price, Relevance, an attribute used for sorting, or a registered listing option.
- **A listing is never left empty.** When every option of a listing would be hidden, the listing shows them all.
- **A hidden default is replaced.** When the default sort is hidden, listings start with the first option shown, ascending, unless that option sets a direction of its own.
- **Listing sort options from other modules.** A module that sorts by a value of its own registers an option with a label, a store-scope Yes/No setting and a default direction. The option appears in Sort By where the store offers it, and in the admin lists a category's and the store's default sort are chosen from.
- **Each option opens in its own direction.** A popularity order opens best-first when the shopper chose no direction; a direction the shopper chose always wins.
- **Cheap on the storefront.** The policy of a store is built once per request from configuration and applied to the toolbar's option list; no table is read. The page-cache key and the toolbar script get the same default direction, so the default order has one URL and one cache entry.

## Requirements

- PHP 8.1 to 8.5.
- `hryvinskyi/magento2-catalog-sorting` ^1.0.
- The platform's Store, Config, Backend, Catalog and CatalogSearch modules (what `composer.json` and `etc/module.xml` declare).

## Installation

```bash
composer require hryvinskyi/magento2-catalog-sorting-options
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

**Stores › Configuration › Catalog › Catalog Sorting › Sort Options**

| Path | Label | Default | Scope |
|---|---|---|---|
| `catalog_sorting/options/overrides` | Sort Options | none | default, website, store view |

A table with one row per option to change:

- **Sort Option**: the option. The list offers the catalog's sort options, Relevance and every registered listing option, then any stored code no listing offers now, marked "(not offered by any listing now)".
- **Show**: No removes the option from Sort By.
- **Label**: replaces its name; blank keeps the current one.
- **Place in List**: moves it (1 = first), counted on the options left after hiding; blank keeps its place.

A save is refused when a row has no option, when Place in List is neither blank nor a whole number from 1, or when one option has two rows. A value at a narrower scope replaces the wider scope's whole table. The default sort itself stays in **Catalog › Storefront › Product Listing Sort by**.

## How it applies

- Plugins on the toolbar's available orders and default direction (`Magento\Catalog\Block\Product\ProductList\Toolbar`) and on the catalog's list of sortable options (`Magento\Catalog\Model\Config`) are declared for the storefront area only.
- In the admin, a category's Available and Default Product Listing Sort By and the store's Product Listing Sort by list every registered listing option, offered in a store or not: a category is shared by stores, and the storefront shows a code only where the store offers it.
- A stored value that cannot be read gives the store no overrides and one log entry; a stored setting never fails a storefront page.

## Extending

### A listing sort option

Declare a virtual type of `Model\ConfigFlagListingSortOption` with `code` (the `product_list_order` value), `label` (marked `translate="true"`), `enabledPath` (a store-scope Yes/No setting) and `defaultDirection` (`asc` or `desc`, default `desc`), and add it to the `options` argument of `Model\ListingSortOptions` with a `sortOrder`. Do this in a global `etc/di.xml`: an area `di.xml` naming this argument replaces the whole list in that area. From `hryvinskyi/magento2-catalog-sorting-bestsellers`:

```xml
<virtualType name="HryvinskyiCatalogSortingBestsellersListingOption"
             type="Hryvinskyi\CatalogSortingOptions\Model\ConfigFlagListingSortOption">
    <arguments>
        <argument name="code" xsi:type="string">bestsellers</argument>
        <argument name="label" xsi:type="string" translate="true">Best Sellers</argument>
        <argument name="enabledPath" xsi:type="string">catalog_sorting/bestsellers/enabled</argument>
        <argument name="defaultDirection" xsi:type="string">desc</argument>
    </arguments>
</virtualType>
<type name="Hryvinskyi\CatalogSortingOptions\Model\ListingSortOptions">
    <arguments>
        <argument name="options" xsi:type="array">
            <item name="bestsellers" xsi:type="object" sortOrder="100">HryvinskyiCatalogSortingBestsellersListingOption</item>
        </argument>
    </arguments>
</type>
```

An option with other rules for being offered implements `Api\ListingSortOptionInterface` itself. Two options with one code fail the construction. The code also needs a field to sort on: register it in the `sortFields` pool of `hryvinskyi/magento2-catalog-sorting`. A code the catalog's list already holds keeps its entry, so a product attribute of the same code is never displaced.

### A sort option the admin can override

The admin's option list comes from the `sources` argument of `Model\SortOptionCatalogue`, each item an `Api\SortOptionSourceInterface` (`getSortOptions()`: code => label) with a `sortOrder`; the first label for a code wins. Registered listing options are already listed. A module that adds a sort code to listings some other way adds a source:

```xml
<type name="Hryvinskyi\CatalogSortingOptions\Model\SortOptionCatalogue">
    <arguments>
        <argument name="sources" xsi:type="array">
            <item name="search_relevance" xsi:type="object" sortOrder="20">Hryvinskyi\CatalogSortingOptions\Model\Source\SearchRelevanceSortOption</item>
        </argument>
    </arguments>
</type>
```

## Public contracts

`Api\ListingSortOptionInterface`, `Api\ListingSortOptionsInterface`, `Api\SortOptionSourceInterface`, `Api\SortOptionCatalogueInterface`, `Api\SortOptionPolicyInterface`, `Api\SortOptionPolicyResolverInterface`, `Api\SortOptionOverridesParserInterface` and `Api\Data\SortOptionOverrideInterface` are `@api`. The `create()` keys of the generated `Api\Data\SortOptionOverrideInterfaceFactory` (`code`, `shown`, `label`, `position`) are part of the contract.
