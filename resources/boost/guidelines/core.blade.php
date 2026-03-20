## HddLaravelHelpers

A comprehensive Laravel package providing CRUD controllers, PrimeVue DataTable backend, model scaffolding, batch operations, infinite scroll search, database backup, WhatsApp integration, and more.

### Key Concepts

- **BaseModel**: Eloquent model base class with SoftDeletes, Auditable, deletion checks, batch operations, and validation helpers.
- **BaseModelWithUlid**: Same as BaseModel but with ULID primary keys.
- **BaseCrudController**: Full CRUD controller with auto-discovered models, data classes, actions, and policies.
- **Auto-Discovery**: Controller name resolves to model, data, action, and policy classes by naming convention (see `hdd-domains` skill).

### Skills

Activate these skills based on the task:

- **hdd-domains**: When creating new models, controllers, or understanding the auto-discovery conventions and directory structure.
- **crud-and-models**: When building CRUD APIs, configuring BaseModel features, writing action classes, or implementing batch operations.
- **primevue-datatable**: When implementing server-side PrimeVue DataTable endpoints with filtering, sorting, joins, and aggregates.
- **infinite-scroll-search**: When building search/autocomplete endpoints with paginated infinite scroll or Arabic-aware searching.
- **using-whatsapp**: When verifying phone numbers via WhatsApp or checking WhatsApp contact status.
- **database-backup**: When implementing database backup/restore workflows or Telegram backup notifications.

### Global Helpers

@verbatim
<code-snippet name="apiResponse helper" lang="php">
// Global function
return apiResponse()->success($data);
return apiResponse()->fail('Error message');

// Static methods on ApiResponse
return ApiResponse::successResponse($data, 201);
return ApiResponse::failedResponse('Error', 400);

// Response macros
return response()->apiSuccess($data);
return response()->apiFail('Error');
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="filledOptional helper" lang="php">
// Check if a value is filled and not a Spatie Data Optional
if (filledOptional($value)) {
    // $value is not null, not empty, and not Optional
}
</code-snippet>
@endverbatim

### Route Macros

@verbatim
<code-snippet name="apiResourceMany route macro" lang="php">
// Register batch/extra routes BEFORE apiResource to avoid conflicts
Route::apiResourceMany('products', ProductController::class);
Route::apiResource('products', ProductController::class);

// With specific actions only
Route::apiResourceMany('products', ProductController::class, only: ['datatable', 'search']);
</code-snippet>
@endverbatim

Available `apiResourceMany` actions: `datatable`, `search`, `storeMany`, `updateMany`, `destroyMany`, `reorder`, `list`, `audits`.

### Middleware

- **ForceJsonResponseMiddleware** (`HassanDomeDenea\HddLaravelHelpers\Middlewares\ForceJsonResponseMiddleware`): Forces all responses to JSON format.
- **AuthenticateFromCookieMiddleware** (`HassanDomeDenea\HddLaravelHelpers\Middlewares\AuthenticateFromCookieMiddleware`): Authenticates requests using cookie-based tokens.
- **PreventCrawlersMiddleware** (`HassanDomeDenea\HddLaravelHelpers\Middlewares\PreventCrawlersMiddleware`): Blocks web crawlers/bots from accessing routes.

### Available Traits

| Trait | Description |
|-------|-------------|
| `HasActiveItems` | Soft-activation with `is_active` column and `disabled` computed attribute |
| `HasCacheableGetters` | Cache expensive getter results with auto-invalidation on model changes |
| `ArabicNormalizable` | Normalize Arabic text variants for flexible searching |
| `HasReordering` | Drag-and-drop reordering with `order_sequence` column and scoped ordering |
| `HasDeletableCheck` | Deletion validation via `canBeDeleted()` and `customDeleteLogic()` |
| `HasCreateAndDeleteMany` | Batch create/update with validation |
| `HasModelRules` | Validation rule builders: `uniqueRule()`, `existsRule()`, `modelExistsRule()` |
| `HasFactoryMethods` | Testing helper `fakeRandomOrNew()` |
| `TransformsToData` | Convert models to Spatie Data objects via `toData()` |
| `HasTranslatableAttributes` | Translatable model attributes support |
| `HasHddBroadcastsEvents` | Broadcasting events support |

### Validation Rules

@verbatim
<code-snippet name="Custom validation rules" lang="php">
use HassanDomeDenea\HddLaravelHelpers\Rules\ModelExistsRule;
use HassanDomeDenea\HddLaravelHelpers\Rules\EnsureEveryIdExistsRule;
use HassanDomeDenea\HddLaravelHelpers\Rules\HexColorRule;

// Check model exists (respects soft deletes)
'category_id' => ['required', new ModelExistsRule(Category::class)],

// Check every ID in array exists
'tag_ids.*' => [new EnsureEveryIdExistsRule(Tag::class)],

// Or use model trait shortcuts
'name' => ['required', Product::uniqueRule('name')],
'category_id' => ['required', Category::existsRule()],

// Validate hex color format
'color' => ['required', new HexColorRule],
</code-snippet>
@endverbatim

### Artisan Commands

- `php artisan basic-model {name} [--create] [--permissions]` — Scaffold model files from custom stubs.
- `php artisan add-permissions {name}` — Add CRUD permission cases to PermissionsEnum and update policy.
- `php artisan backup:telegram` — Create database backup and send to Telegram channel.

### Schema Macros

@verbatim
<code-snippet name="dropColumnsIfExist macro" lang="php">
// Safely drop columns in migrations (no error if column doesn't exist)
Schema::dropColumnsIfExist('products', ['old_column', 'deprecated_field']);
</code-snippet>
@endverbatim
