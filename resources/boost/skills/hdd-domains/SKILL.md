---
name: hdd-domains
description: Auto-discovery conventions, project directory structure, and scaffolding commands for HddLaravelHelpers.
---

# HDD Domains - Auto-Discovery & Project Structure

## When to use this skill
Use this skill when creating new models, controllers, data classes, actions, or policies; when understanding how auto-discovery resolves class names; or when scaffolding a new domain entity.

## Directory Structure Conventions

HddLaravelHelpers uses naming conventions to auto-discover related classes from a controller name:

```
app/
  Http/Controllers/
    ProductController.php        # Controller
  Models/
    Product.php                  # Model (auto-discovered from controller name)
  Data/
    Product/                     # Data subfolder (when isolate-in-subfolders = true)
      ProductData.php            # Main Data class
      StoreProductData.php       # Store-specific Data class
      UpdateProductData.php      # Update-specific Data class
  Actions/
    Product/                     # Actions subfolder (when isolate-in-subfolders = true)
      CreateProductAction.php    # Create action
      UpdateProductAction.php    # Update action
  Policies/
    ProductPolicy.php            # Policy (auto-discovered from controller name)
```

When `isolate-in-subfolders` is `false`, Data and Action classes are placed directly in `app/Data/` and `app/Actions/` without subfolders.

## Auto-Discovery Logic (PathHelpers)

The `PathHelpers` class resolves related classes from the model class name:

### Model to Data Class
`PathHelpers::getDataClassFromModelClass(string $modelClassName, string $prefix = '')`

Resolution order (with `isolate-in-subfolders = true`):
1. `App\Data\{ModelName}\{Prefix}{ModelName}Data` (e.g. `App\Data\Product\StoreProductData`)
2. `App\Data\{ModelName}\{Prefix}{ModelName}` (e.g. `App\Data\Product\StoreProduct`)
3. Returns `false` if neither exists

Without prefix, resolves to: `App\Data\Product\ProductData` or `App\Data\Product\Product`

### Model to Action Class
`PathHelpers::getCreateActionClassFromModelClass(string $modelClassName)`

Resolution order:
1. `App\Actions\{ModelName}\Create{ModelName}Action`
2. `App\Actions\{ModelName}\Create{ModelName}`
3. Returns `false` if neither exists

Same pattern for `getUpdateActionClassFromModelClass` with `Update` prefix.

### Controller to Model
The controller resolves its model class by stripping the `Controller` suffix:
```php
// In BaseCrudController::getModalClass()
'App\Models\\' . str(static::class)->afterLast('\\')->before('Controller');
// ProductController -> App\Models\Product
```

### Controller to Policy
```php
// In BaseCrudController::getPolicyClass()
'App\Policies\\' . str(static::class)->afterLast('\\')->before('Controller')->append('Policy');
// ProductController -> App\Policies\ProductPolicy
```

## Config Toggles

In `config/hdd-laravel-helpers.php`:

```php
'data-classes' => [
    'isolate-in-subfolders' => env("HDD_DATA_CLASSES_ISOLATE_IN_SUBFOLDERS", true),
],
'action-classes' => [
    'isolate-in-subfolders' => env("HDD_ACTION_CLASSES_ISOLATE_IN_SUBFOLDERS", true),
],
```

## Scaffolding Commands

### `basic-model` - Generate Model Scaffold

```bash
php artisan basic-model Product
php artisan basic-model Product --create    # Also runs make:model --all
php artisan basic-model Product --permissions  # Also generates permissions
```

Generates from custom stubs in `stubs/custom/`:
- Controller (`controller.stub` -> `app/Http/Controllers/ProductController.php`)
- Model (`model.stub` -> `app/Models/Product.php`)
- Factory (`factory.stub` -> `database/factories/ProductFactory.php`)
- Migration (`migration.stub` -> `database/migrations/...create_products_table.php`)
- Seeder (`seeder.stub` -> `database/seeders/ProductSeeder.php`)
- Store/Update Requests (`storeRequest.stub`, `updateRequest.stub` -> `app/Http/Requests/`)

Use `--only=controller,model` to generate specific files only.

### `add-permissions` - Add CRUD Permissions

```bash
php artisan add-permissions Product
```

Adds 6 permission cases to your `PermissionsEnum`:
- `CreateProduct`, `UpdateProduct`, `ViewProduct`, `DeleteProduct`, `RestoreProduct`, `ForceDeleteProduct`

Also updates the corresponding `ProductPolicy.php` to use these permission checks.

## Route Registration Pattern

Always register `apiResourceMany()` **before** `apiResource()` to avoid route conflicts:

```php
use App\Http\Controllers\ProductController;

// CORRECT order - many routes first
Route::apiResourceMany('products', ProductController::class);
Route::apiResource('products', ProductController::class);

// With limited actions
Route::apiResourceMany('products', ProductController::class, only: ['datatable', 'search', 'destroyMany']);
Route::apiResource('products', ProductController::class)->except(['destroy']);
```

The `apiResourceMany()` macro registers these routes:
- `GET products/datatable` - PrimeVue DataTable endpoint
- `GET products/list` - Simple list endpoint
- `GET products/search` - Infinite scroll search
- `PUT products/reorder` - Reorder items
- `POST products/many` - Batch create
- `PUT products` - Batch update
- `DELETE products` - Batch delete
- `GET products/{product}/audits` - Audit trail
