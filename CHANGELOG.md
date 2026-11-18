# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-18

### Added
- **Sort Options** (`catalog_sorting/options/overrides`): hide, rename and move the options of the storefront Sort By
  list per store view, with a save that refuses rows the storefront could not use.
- A listing whose options would all be hidden shows them all; a hidden default sort is replaced by the first option
  shown.
- The `options` pool of `Model\ListingSortOptions`: sort codes other modules register, offered in the stores whose
  Yes/No setting is on, in the admin sort lists and in the options the admin can override;
  `Model\ConfigFlagListingSortOption` declares one in `di.xml`.
- A default direction per listing option, applied to the toolbar, its script and the page-cache key when the shopper
  chose no direction.
- The `sources` pool of `Model\SortOptionCatalogue` for the options the admin can pick from.
