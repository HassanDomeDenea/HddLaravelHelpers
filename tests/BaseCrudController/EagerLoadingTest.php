<?php

use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\ConstrainedWorkerController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\PlainWorkerController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\WorkerController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\WorkerWithPreloadedCategoryController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Category;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Tag;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Worker;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('workers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('category_id')->constrained();
        $table->timestamps();
    });
    Schema::create('tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('worker_id')->constrained();
        $table->timestamps();
    });

    Route::get('/workers/datatable', [WorkerController::class, 'datatable']);
    Route::get('/workers/list', [WorkerController::class, 'list']);
    Route::get('/workers', [WorkerController::class, 'index']);
    Route::post('/workers', [WorkerController::class, 'store']);
    Route::put('/workers/{worker}', [WorkerController::class, 'update']);
    Route::get('/workers/{id}', [WorkerController::class, 'show']);

    Route::get('/plain-workers/{id}', [PlainWorkerController::class, 'show']);
    Route::get('/plain-workers', [PlainWorkerController::class, 'index']);
    Route::post('/workers-preloaded', [WorkerWithPreloadedCategoryController::class, 'store']);
    Route::get('/workers-constrained/{id}', [ConstrainedWorkerController::class, 'show']);
});

function eagerLoadCategory(string $name = 'Electrical'): Category
{
    return Category::create(['name' => $name]);
}

function eagerLoadWorker(?Category $category = null, string $name = 'Hassan'): Worker
{
    return Worker::create([
        'name' => $name,
        'category_id' => ($category ?? eagerLoadCategory())->id,
    ]);
}

function eagerLoadTag(Worker $worker, string $name): Tag
{
    return Tag::create([
        'name' => $name,
        'worker_id' => $worker->id,
    ]);
}

/**
 * @return list<string>
 */
function captureSql(callable $callback): array
{
    $sql = [];
    Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$sql) {
        $sql[] = $event->sql;
    });
    $callback();

    return $sql;
}

function queriesForTable(array $sql, string $table): array
{
    $pattern = '/\bfrom\s+["`]?'.preg_quote($table, '/').'["`]?/i';

    return array_values(array_filter($sql, fn (string $query) => preg_match($pattern, $query)));
}

it('returns the declared relation on show', function () {
    $category = eagerLoadCategory('Electrical');
    $worker = eagerLoadWorker($category, 'Hassan');

    $this->getJson("/workers/{$worker->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $worker->id)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.category.name', 'Electrical');
});

it('serialises an undeclared relation as null on show', function () {
    $worker = eagerLoadWorker(eagerLoadCategory('Electrical'), 'Hassan');

    $this->getJson("/plain-workers/{$worker->id}")
        ->assertOk()
        ->assertJsonPath('data.category', null);
});

it('returns the declared relation on store', function () {
    $category = eagerLoadCategory('Plumbing');

    $this->postJson('/workers', [
        'name' => 'New Worker',
        'category_id' => $category->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Worker')
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.category.name', 'Plumbing');
});

it('returns the declared relation on update', function () {
    $original = eagerLoadCategory('Electrical');
    $updated = eagerLoadCategory('HVAC');
    $worker = eagerLoadWorker($original, 'Hassan');

    $this->putJson("/workers/{$worker->id}", [
        'name' => 'Updated Worker',
        'category_id' => $updated->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Worker')
        ->assertJsonPath('data.category.id', $updated->id)
        ->assertJsonPath('data.category.name', 'HVAC');
});

it('returns the declared relation on listing endpoints', function (string $uri, string $itemsPath) {
    $category = eagerLoadCategory('Electrical');
    eagerLoadWorker($category, 'Hassan');

    $this->getJson($uri)
        ->assertOk()
        ->assertJsonPath("{$itemsPath}.0.category.name", 'Electrical');
})->with([
    'index' => ['/workers?'.http_build_query(['page' => 1, 'perPage' => -1, 'options' => ['onlyRequestedColumns' => false]]), 'data.data'],
    'datatable' => ['/workers/datatable?'.http_build_query(['page' => 1, 'perPage' => -1, 'options' => ['onlyRequestedColumns' => false]]), 'data.data'],
    'list' => ['/workers/list', 'data'],
]);

it('merges declared eager loads with request includes', function () {
    $category = eagerLoadCategory('Electrical');
    $worker = eagerLoadWorker($category, 'Hassan');
    eagerLoadTag($worker, 'urgent');
    eagerLoadTag($worker, 'night-shift');

    $this->getJson("/workers/{$worker->id}?include=tags")
        ->assertOk()
        ->assertJsonPath('data.category.name', 'Electrical')
        ->assertJsonPath('data.tags.0.name', 'urgent')
        ->assertJsonPath('data.tags.1.name', 'night-shift');
});

it('does not load request includes unless they are asked for', function () {
    $worker = eagerLoadWorker();
    eagerLoadTag($worker, 'urgent');

    $this->getJson("/workers/{$worker->id}")
        ->assertOk()
        ->assertJsonPath('data.category.name', 'Electrical')
        ->assertJsonPath('data.tags', null);
});

it('eager-loads the declared relation once for an index of N records', function () {
    $count = 5;
    for ($i = 1; $i <= $count; $i++) {
        eagerLoadWorker(eagerLoadCategory("Category {$i}"), "Worker {$i}");
    }

    $sql = captureSql(function () {
        $this->getJson('/workers?'.http_build_query([
            'page' => 1,
            'perPage' => -1,
            'options' => ['onlyRequestedColumns' => false],
        ]))->assertOk();
    });

    expect(queriesForTable($sql, 'categories'))->toHaveCount(1)
        ->and(queriesForTable($sql, 'workers'))->not->toBeEmpty();
});

it('does not query the relation on an index when nothing is declared', function () {
    $count = 5;
    for ($i = 1; $i <= $count; $i++) {
        eagerLoadWorker(eagerLoadCategory("Category {$i}"), "Worker {$i}");
    }

    $sql = captureSql(function () {
        $this->getJson('/plain-workers?'.http_build_query([
            'page' => 1,
            'perPage' => -1,
            'options' => ['onlyRequestedColumns' => false],
        ]))
            ->assertOk()
            ->assertJsonPath('data.data.0.category', null);
    });

    expect(queriesForTable($sql, 'categories'))->toHaveCount(0);
});

it('forwards relation names and closures to Eloquent', function () {
    $constraint = fn ($query) => $query->where('name', 'keep');
    $controller = new class($constraint) extends WorkerController
    {
        public function __construct(private mixed $constraint) {}

        protected function getEagerLoads(): array
        {
            return [
                'category',
                'tags' => $this->constraint,
            ];
        }
    };

    $eagerLoads = $controller->queryBuilder()->getEagerLoads();

    expect($eagerLoads)
        ->toHaveKey('category')
        ->toHaveKey('tags')
        ->and($eagerLoads['tags'])->toBeCallable();
});

it('applies constrained eager loads from getEagerLoads', function () {
    $worker = eagerLoadWorker();
    eagerLoadTag($worker, 'keep');
    eagerLoadTag($worker, 'drop');

    $this->getJson("/workers-constrained/{$worker->id}")
        ->assertOk()
        ->assertJsonPath('data.category.name', 'Electrical')
        ->assertJsonCount(1, 'data.tags')
        ->assertJsonPath('data.tags.0.name', 'keep');
});

it('does not reload a relation that an action already loaded', function () {
    $category = eagerLoadCategory('Electrical');

    $sql = captureSql(function () use ($category) {
        $this->postJson('/workers-preloaded', [
            'name' => 'Preloaded',
            'category_id' => $category->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Electrical');
    });

    expect(queriesForTable($sql, 'categories'))->toHaveCount(1);
});
