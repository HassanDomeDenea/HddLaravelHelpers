---
name: crud-and-models
description: Build CRUD APIs using BaseCrudController, BaseModel, action classes, batch operations, and policies.
---

# CRUD & Models

## When to use this skill
Use this skill when building API controllers with CRUD operations, configuring BaseModel features, writing action classes, implementing batch operations, or setting up deletion checks and reordering.

## BaseModel

`BaseModel` extends Eloquent Model and includes these traits by default:
- `SoftDeletes` - Soft deletion support
- `Auditable` (owen-it/laravel-auditing) - Audit trail
- `TransformsToData` - Convert to Spatie Data objects via `$model->toData()`
- `HasDeletableCheck` - Deletion validation
- `HasCreateAndDeleteMany` - Batch create/delete
- `HasModelRules` - Validation rule helpers
- `HasFactoryMethods` - Testing factory helpers

```php
use HassanDomeDenea\HddLaravelHelpers\BaseModel;

class Product extends BaseModel
{
    protected $guarded = ['id'];

    // Optional: override deletion check
    public function canBeDeleted(): bool|string
    {
        if ($this->orders()->exists()) {
            return 'Cannot delete product with existing orders';
        }
        return true;
    }
}
```

### BaseModelWithUlid

For models using ULID primary keys instead of auto-increment:

```php
use HassanDomeDenea\HddLaravelHelpers\BaseModelWithUlid;

class Invoice extends BaseModelWithUlid
{
    protected $guarded = ['id'];
}
```

## BaseCrudController

### Properties

```php
use HassanDomeDenea\HddLaravelHelpers\BaseCrudController;

class ProductController extends BaseCrudController
{
    // All are optional - auto-discovered from controller name if not set
    public string|null $modelClass = Product::class;
    public ?string $dataClass = ProductData::class;
    public ?string $policyClass = ProductPolicy::class;
    public ?string $createActionClass = CreateProductAction::class;
    public ?string $updateActionClass = UpdateProductAction::class;
    public ?string $storeFormRequestClass = null;  // Alternative to Data class
    public ?string $updateFormRequestClass = null;  // Alternative to Data class

    protected array $allowedIncludes = ['category', 'tags'];
    protected array $allowedFilters = ['name', 'category_id'];
}
```

### Built-in Endpoints

| Method | Endpoint | Controller Method | Description |
|--------|----------|------------------|-------------|
| GET | `/products/datatable` | `datatable()` / `index()` | PrimeVue DataTable |
| GET | `/products/list` | `list()` | Simple list (all records) |
| GET | `/products/search` | `search()` | Infinite scroll search |
| GET | `/products/{id}` | `show()` | Single record |
| POST | `/products` | `store()` | Create one |
| PUT | `/products/{product}` | `update()` | Update one |
| DELETE | `/products/{id}` | `destroy()` | Delete one |
| POST | `/products/many` | `storeMany()` | Batch create |
| PUT | `/products` | `updateMany()` | Batch update |
| DELETE | `/products` | `destroyMany()` | Batch delete |
| PUT | `/products/reorder` | `reorder()` | Reorder items |
| GET | `/products/{id}/audits` | `audits()` | Audit trail |

### Overriding Endpoints

```php
class ProductController extends BaseCrudController
{
    public function index(DataTable $dt): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $dt->setModel($this->getQueryBuilder());
        $dt->setDataClass($this->getDataClass());
        // Add custom DataTable configuration here
        return ApiResponse::successResponse($dt->proceed());
    }

    public function search(InfiniteScrollRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);
        $query = $this->getQueryBuilder();
        return (new InfiniteScrollSearcherService($request, $query, ['name' => 'regexp', 'sku' => 'like']))
            ->setDataClass($this->getDataClass())
            ->proceed();
    }
}
```

## Action Classes

Action classes encapsulate create/update logic. They are auto-discovered by convention or set explicitly.

### Create Action

```php
namespace App\Actions\Product;

class CreateProductAction
{
    public function handle(array $attributes): Product
    {
        $product = Product::create($attributes);
        // Additional logic (attach tags, fire events, etc.)
        return $product;
    }
}
```

The `handle()` method's first parameter type determines how request data is passed:
- `array $attributes` - receives validated array
- `StoreProductData $data` - receives Spatie Data object

### Update Action

```php
namespace App\Actions\Product;

class UpdateProductAction
{
    public function handle(Product $model, array $attributes): Product
    {
        $model->update($attributes);
        // Additional logic
        return $model;
    }
}
```

## Batch Operations

### storeMany
Request body: `{ "data": [{ "name": "A" }, { "name": "B" }] }`
Returns: array of created IDs with status 201.

### updateMany
Request body: `{ "data": [{ "id": 1, "name": "Updated A" }, { "id": 2, "name": "Updated B" }] }`
Returns: array of updated IDs. All updates run in a database transaction.

### destroyMany
Request body: `{ "ids": [1, 2, 3] }`
Calls `checkAndDeleteMany()` which validates each model's `canBeDeleted()` before deletion.

## HasDeletableCheck Trait

```php
class Product extends BaseModel
{
    // Return true to allow deletion, or error message string to prevent
    public function canBeDeleted(): bool|string
    {
        if ($this->orders()->exists()) {
            return 'Product has orders and cannot be deleted';
        }
        return true;
    }

    // Override for custom delete logic (e.g., cascade)
    public function customDeleteLogic(): void
    {
        $this->tags()->detach();
        $this->delete();
    }
}
```

- `checkAndDelete()` - Validates then deletes single model
- `checkAndDeleteMany(array $ids)` - Validates then deletes multiple models in transaction

## HasReordering Trait

Add to models that need drag-and-drop reordering:

```php
use HassanDomeDenea\HddLaravelHelpers\Traits\HasReordering;

class MenuItem extends BaseModel
{
    use HasReordering;

    public function getOrderColumnName(): string
    {
        return 'sort_order'; // default: 'order_sequence'
    }

    // Scope reordering within a parent (e.g., items in same menu)
    public function getOrderScopedColumns(): array
    {
        return ['menu_id']; // default: []
    }
}
```

Reorder request body: `{ "from_order": 2, "to_order": 5, "scopedValues": [1] }`

The `reorderSequence()` method handles shifting items between positions atomically.

## HasModelRules Trait

Provides validation rule builders for models:

```php
// In a FormRequest or Data class
'name' => ['required', Product::uniqueRule('name')],      // unique, ignoring soft-deleted
'category_id' => ['required', Category::existsRule()],    // exists, ignoring soft-deleted
'category_id' => ['required', Category::modelExistsRule()], // custom ModelExistsRule
'tag_ids' => ['required', 'array'],
'tag_ids.*' => [Tag::existsMultiRule()],                  // EnsureEveryIdExistsRule
```

## Policy Methods

The controller checks these policy methods:

| Method | Used by |
|--------|---------|
| `viewAny` | `index()`, `datatable()`, `search()` |
| `viewList` | `list()` (falls back to `viewAny`) |
| `view` | `show()` |
| `create` | `store()`, `storeMany()` |
| `update` | `update()` |
| `updateMany` | `updateMany()` |
| `delete` | `destroy()` |
| `deleteMany` | `destroyMany()` |
| `reorder` | `reorder()` |

## ApiResponse

```php
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;

// Static factory methods
return ApiResponse::successResponse($data);           // 200
return ApiResponse::successResponse($data, 201);      // 201
return ApiResponse::failedResponse('Error message');   // 400
return ApiResponse::failedResponse('Not found', 404); // 404

// Instance methods
return apiResponse()->success($data);
return apiResponse()->fail('Error message');

// Response macros
return response()->apiSuccess($data);
return response()->apiFail('Error message');
```

## Complete Example

```php
// app/Models/Product.php
class Product extends BaseModel
{
    protected $guarded = ['id'];

    public function category() { return $this->belongsTo(Category::class); }
    public function tags() { return $this->belongsToMany(Tag::class); }

    public function canBeDeleted(): bool|string
    {
        return $this->orders()->doesntExist() ?: 'Has orders';
    }
}

// app/Data/Product/ProductData.php
class ProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public int $category_id,
    ) {}
}

// app/Data/Product/StoreProductData.php
class StoreProductData extends Data
{
    public function __construct(
        public string $name,
        public float $price,
        public int $category_id,
    ) {}
}

// app/Actions/Product/CreateProductAction.php
class CreateProductAction
{
    public function handle(array $attributes): Product
    {
        return Product::create($attributes);
    }
}

// app/Http/Controllers/ProductController.php
class ProductController extends BaseCrudController
{
    protected array $allowedIncludes = ['category', 'tags'];
}

// routes/api.php
Route::apiResourceMany('products', ProductController::class);
Route::apiResource('products', ProductController::class);
```
