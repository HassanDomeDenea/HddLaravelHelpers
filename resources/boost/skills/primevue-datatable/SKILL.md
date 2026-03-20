---
name: primevue-datatable
description: Server-side PrimeVue DataTable processing with filtering, sorting, joins, and aggregates.
---

# PrimeVue DataTable Backend

## When to use this skill
Use this skill when implementing server-side DataTable endpoints, configuring filters, joins, relation aggregates, or customizing the DataTable response.

## Basic Usage

### Static Factory (standalone usage)

```php
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;

$result = DataTable::using(Product::class)->proceed();
return ApiResponse::successResponse($result);
```

### Dependency Injection (in BaseCrudController)

```php
public function index(DataTable $dt): JsonResponse
{
    $dt->setModel($this->getQueryBuilder());
    $dt->setDataClass($this->getDataClass());
    return ApiResponse::successResponse($dt->proceed());
}
```

## Field Types (FieldType Enum)

Fields are declared in the frontend payload and tell the backend how to process each column:

| FieldType | Description |
|-----------|-------------|
| `main` | Regular column on the main table |
| `json` | JSON column accessed with `->` notation |
| `jsonArray` | JSON array column (for `whereIn` uses `whereJsonContains`) |
| `relation` | Eager-loaded relation field (e.g., `category.name`) |
| `relationCount` | Relationship count (e.g., `orders_count`) |
| `relationAggregate` | Relationship aggregate (e.g., `orders.total_sum`) |
| `relationMany` | HasMany/BelongsToMany filtering via `whereHas` |
| `mainCount` | Count on main table |
| `custom` | Custom computed field |

## Filter Match Modes (FilterMatchMode Enum)

### String Matching
- `contains` - LIKE %value%
- `notContains` - NOT LIKE %value%
- `containsAll` - All space-separated words must match (LIKE each)
- `containsAny` - Any space-separated word may match (OR LIKE each)
- `startsWith` - LIKE value%
- `endsWith` - LIKE %value
- `equals` - Exact match (handles `"true"`/`"false"` as booleans)
- `notEquals` - Not equal

### Set Matching
- `whereIn` - Value in array (for jsonArray fields, uses `whereJsonContains`)
- `whereNotIn` - Value not in array

### Null Checks
- `isNull` - Column IS NULL
- `isNotNull` - Column IS NOT NULL

### Range
- `between` - Between two numeric values `[min, max]`
- `notBetween` - Not between two numeric values

### Date Operations
- `dateIs` / `dateIsNot` - Exact date match
- `dateBefore` / `dateAfter` - Before/after date
- `dateLte` / `dateIsOrBefore` - On or before date
- `dateGte` / `dateIsOrAfter` - On or after date
- `dateBetween` / `dateNotBetween` - Date range `[start, end]`

### Numeric Comparisons
- `lessThan` (lt) / `lessThanOrEquals` (lte)
- `greaterThan` (gt) / `greaterThanOrEquals` (gte)

## Joining Relations

Use `joinRelation()` to LEFT JOIN a relation table (via PowerJoins) for sorting/filtering on related columns:

```php
$dt = DataTable::using(Product::class);
$dt->joinRelation('category');           // Single relation
$dt->joinRelation(['category', 'brand']); // Multiple relations
$dt->joinRelation('category.parent');    // Nested relation
```

For morph relations, pass the morph type:
```php
$dt->joinRelation('commentable', morphableTo: 'product');
```

## Loading Relations

### Eager Load (for display, not filtering/sorting)
```php
$dt->loadRelation('category');
$dt->loadRelation(['category', 'tags']);
```

### Relation Counts
```php
$dt->loadRelationCount('orders');
$dt->loadRelationCount(['orders', 'reviews']);
```

### Relation Aggregates
The `joinRelationAggregate()` method is used internally based on field type declarations. Fields named like `orders_count` with `FieldType::relationCount` or `orders.total_sum` with `FieldType::relationAggregate` are auto-processed.

## Query Modification

### Modify the base query
```php
$dt->modifyQuery(function (Builder $query) {
    $query->where('is_active', true);
    $query->withTrashed();
});
```

### Add custom select expressions
```php
use Tpetry\QueryExpressions\Function\Aggregate\Sum;

$dt->addSelect(new Sum('order_items.quantity'));
```

## Data Transformation

### Set a Data class for response transformation
```php
$dt->setDataClass(ProductData::class);
```

### Modify the items collection after retrieval
```php
$dt->modifyItemsCollection(function (Collection $items) {
    return $items->map(function ($item) {
        $item->computed_field = calculateSomething($item);
        return $item;
    });
});
```

## Grouped Filters

Grouped filters support nested AND/OR logic. They are sent from the frontend as a tree structure:

```json
{
    "groupedFilter": {
        "operator": "and",
        "fields": [
            { "field": "name", "matchMode": "contains", "value": "phone" },
            {
                "operator": "or",
                "fields": [
                    { "field": "price", "matchMode": "gt", "value": 100 },
                    { "field": "stock", "matchMode": "equals", "value": 0 }
                ]
            }
        ]
    }
}
```

This generates: `WHERE name LIKE '%phone%' AND (price > 100 OR stock = 0)`

## Response Structure (ResponseData)

```json
{
    "data": [],
    "current_page": 1,
    "from": 1,
    "to": 10,
    "per_page": 10,
    "last_page": 5,
    "total": 50,
    "total_without_filters": 120
}
```

- `total` - Count after filters applied
- `total_without_filters` - Count before user filters (but after fixed filters)

## Complete Example

```php
class OrderController extends BaseCrudController
{
    protected array $allowedIncludes = ['customer', 'items'];

    public function index(DataTable $dt): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $dt->setModel($this->getQueryBuilder());
        $dt->setDataClass(OrderData::class);

        // Join for sorting/filtering on related columns
        $dt->joinRelation('customer');

        // Eager load for display
        $dt->loadRelation('items');

        // Add aggregate columns
        $dt->loadRelationCount('items');

        // Filter base query
        $dt->modifyQuery(fn(Builder $q) => $q->where('status', '!=', 'draft'));

        // Transform items
        $dt->modifyItemsCollection(function (Collection $items) {
            return $items->each(fn($item) => $item->total_display = number_format($item->total, 2));
        });

        return ApiResponse::successResponse($dt->proceed());
    }
}
```
