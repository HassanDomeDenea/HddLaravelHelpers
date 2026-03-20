---
name: infinite-scroll-search
description: Infinite scroll search and autocomplete endpoints using InfiniteScrollSearcherService.
---

# Infinite Scroll Search

## When to use this skill
Use this skill when building search/autocomplete endpoints with paginated infinite scroll, Arabic-aware searching, or custom filter strategies.

## Basic Usage

The `BaseCrudController` provides a default `search()` endpoint that uses `InfiniteScrollSearcherService`:

```php
// Default implementation in BaseCrudController
public function search(InfiniteScrollRequest $request): JsonResponse
{
    Gate::authorize('viewAny', $this->getModalClass());
    $query = $this->getQueryBuilder();
    return (new InfiniteScrollSearcherService($request, $query, ['name' => 'regexp']))
        ->setDataClass($this->getDataClass())
        ->proceed();
}
```

## Constructor

```php
new InfiniteScrollSearcherService(
    InfiniteScrollRequest $request,
    Builder|QueryBuilder $modelQuery,
    array $filterColumns = ['name' => 'regexp'],
    string $filtersBoolean = 'or',          // 'or' or 'and'
    ?string $defaultOrderColumn = null,
    string $defaultOrderDirection = 'asc'
);
```

## Filter Strategies

The `$filterColumns` array maps column names to filter strategies:

| Strategy | Description |
|----------|-------------|
| `regexp` | Arabic-aware regex matching using `SearchArabicNamesUsingRegexp` |
| `like` | Standard SQL LIKE `%value%` |
| `equals` | Exact match |
| `containsAny` | Space-separated words, any word may match (OR LIKE each) |

```php
// Search by name (Arabic-aware) OR by SKU (exact match)
$filterColumns = [
    'name' => 'regexp',
    'sku' => 'equals',
];

// Search by name OR description (both LIKE)
$filterColumns = [
    'name' => 'like',
    'description' => 'like',
];
```

If the value is not one of the recognized strategies, it is treated as a column name with LIKE matching.

## Request Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | `''` | Search query text |
| `offset` | int | `0` | Pagination offset |
| `limit` | int | `25` | Items per page |
| `only_id` | bool | `false` | Search by ID instead of name |
| `multiple_ids` | bool | `false` | Search by multiple IDs (whereIn) |
| `id_field` | string | `'id'` | Column to use for ID lookups |
| `order_by` | string | null | Custom order column |
| `order_by_direction` | string | `'asc'` | Order direction |

## Chaining Methods

```php
$searcher = new InfiniteScrollSearcherService($request, $query, ['name' => 'regexp']);

$searcher
    ->setDataClass(ProductData::class)  // Transform results with Data class
    ->includeId(true)                   // Also match against ID column
    ->setIdColumn('code')               // Custom ID column (default: 'id')
    ->proceed();                        // Execute and return JsonResponse
```

## Arabic-Aware Search (SearchArabicNamesUsingRegexp)

The `regexp` strategy uses `SearchArabicNamesUsingRegexp::convertNameToRegexp()` to handle Arabic character variations:

- Alef variants: `أ`, `ا`, `آ`, `إ` are treated as equivalent
- Ya variants: `ي`, `ى` are treated as equivalent
- Ha/Tah Marbuta variants: `ه`, `ة` are treated as equivalent
- Diacritical marks (harakat) are ignored

This allows searching for "احمد" to match "أحمد", "أَحْمَد", "احمد", etc.

## Response Format

```json
{
    "success": true,
    "data": {
        "items": [
            { "id": 1, "name": "Product A" },
            { "id": 2, "name": "Product B" }
        ],
        "total": 42
    }
}
```

## Custom Search Override Example

```php
class ProductController extends BaseCrudController
{
    public function search(InfiniteScrollRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::query()
            ->with('category')
            ->where('is_active', true);

        return (new InfiniteScrollSearcherService(
            $request,
            $query,
            filterColumns: [
                'name' => 'regexp',
                'sku' => 'like',
                'barcode' => 'equals',
            ],
            filtersBoolean: 'or',
            defaultOrderColumn: 'name',
        ))
            ->setDataClass(ProductSearchData::class)
            ->includeId(true)
            ->proceed();
    }
}
```
