# Changelog

All notable changes to `HddLaravelHelpers` will be documented in this file.

## Unreleased

### Security

- `BaseCrudController::audits()` now authorizes like `show()`: it requires `view` on the record, so a field's change history is no longer readable by any authenticated user. When a `viewAudits` ability is defined (a policy method, or app wide through `Gate::define`), it is required as well and receives the record and the requested `field`. Controllers opt out with a falsy `$policyClass`, as for the other actions, or override `authorizeAudits()`. Models without a policy, or whose policy has no `view`, now answer 403 on `audits` to everyone `Gate::before` does not let through.

### Added

- Declarative eager loading on `BaseCrudController` via `protected array $with` and overridable `getEagerLoads()`. Relations listed there are always loaded on `index`, `datatable`, `list`, `search`, `show`, `store`, and `update`, and compose with Spatie Query Builder `allowedIncludes` / `?include=`. Hand-written `show()` overrides that only added `->with(...)` can be deleted. `store` and `update` load the same relations via `loadMissing()`, which those `show()` overrides never covered.

