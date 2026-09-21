# Changelog

All notable changes to `HddLaravelHelpers` will be documented in this file.

## Unreleased

### Added

- Declarative eager loading on `BaseCrudController` via `protected array $with` and overridable `getEagerLoads()`. Relations listed there are always loaded on `index`, `datatable`, `list`, `search`, `show`, `store`, and `update`, and compose with Spatie Query Builder `allowedIncludes` / `?include=`. Hand-written `show()` overrides that only added `->with(...)` can be deleted. `store` and `update` load the same relations via `loadMissing()`, which those `show()` overrides never covered.

