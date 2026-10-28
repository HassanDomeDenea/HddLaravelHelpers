<?php

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\Tests\Data\Worker\WorkerData;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Category;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;

/**
 * @param  list<array{field: string, direction: string}>  $sorts
 */
function pagedWorkersDataTable(int $perPage, int $page = 1, array $sorts = [['field' => 'name', 'direction' => 'asc']]): DataTable
{
    return (new DataTable)
        ->setModel(Worker::query())
        ->setDataClass(WorkerData::class)
        ->setPayload(new Fluent([
            'page' => $page,
            'perPage' => $perPage,
            'fields' => [['name' => 'name']],
            'sorts' => $sorts,
        ]));
}

/**
 * @return list<string>
 */
function selectQueriesOf(callable $callback): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_starts_with($query->sql, 'select "workers".*') || str_starts_with($query->sql, 'select * from "workers"')) {
            $queries[] = $query->sql;
        }
    });
    $callback();

    return $queries;
}

beforeEach(function (): void {
    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('workers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->foreignId('category_id')->constrained();
        $table->timestamps();
    });

    $category = Category::create(['name' => 'Electrical']);
    // Three names for thirty workers, so a sort by name ties ten rows at a time.
    Worker::insert(array_map(
        fn (int $number): array => ['name' => 'Worker '.($number % 3), 'category_id' => $category->id],
        range(1, 30),
    ));
});

afterEach(function (): void {
    Model::clearBootedModels();
});

it('refuses to load every row when they are more than one request may hold', function (): void {
    config()->set('hdd-laravel-helpers.datatable.max_rows_per_request', 20);

    expect(fn () => pagedWorkersDataTable(-1)->proceed())
        ->toThrow(ValidationException::class, 'This asks for 30 rows at once, more than the 20 that can be loaded together.');
});

it('refuses a page larger than one request may hold', function (): void {
    config()->set('hdd-laravel-helpers.datatable.max_rows_per_request', 20);

    expect(fn () => pagedWorkersDataTable(25)->proceed())->toThrow(ValidationException::class);
});

it('loads every row while they fit, and without a limit', function (?int $maxRows): void {
    config()->set('hdd-laravel-helpers.datatable.max_rows_per_request', $maxRows);

    expect(pagedWorkersDataTable(-1)->proceed()->data)->toHaveCount(30);
})->with([30, null]);

it('gathers every row exactly once when paging through tied sort values', function (): void {
    $ids = collect(range(1, 6))
        ->flatMap(fn (int $page) => pagedWorkersDataTable(5, $page)->proceed()->data->pluck('id'));

    expect($ids->all())->toBe(Worker::query()->orderBy('name')->orderBy('id')->pluck('id')->all());
});

it('breaks ties by the primary key in the direction of the last sort', function (): void {
    $queries = selectQueriesOf(fn () => pagedWorkersDataTable(5, sorts: [['field' => 'name', 'direction' => 'desc']])->proceed());

    expect($queries)->toHaveCount(1)
        ->and($queries[0])->toEndWith('order by "workers"."name" desc, "workers"."id" desc limit 5 offset 0');
});

it('leaves a sort on the primary key alone', function (): void {
    $queries = selectQueriesOf(fn () => pagedWorkersDataTable(5, sorts: [['field' => 'id', 'direction' => 'desc']])->proceed());

    expect($queries[0])->toEndWith('order by "workers"."id" desc limit 5 offset 0');
});

it('hands back a query that totals can still reorder away', function (): void {
    $dataTable = pagedWorkersDataTable(5);
    $dataTable->proceed();

    expect($dataTable->getQuery()->reorder()->toSql())->not->toContain('order by');
});

it('keeps a model ordering scope ahead of the primary key', function (): void {
    Worker::addGlobalScope('order', fn (Builder $query) => $query->orderBy('name', 'desc'));

    $queries = selectQueriesOf(fn () => pagedWorkersDataTable(5, sorts: [])->proceed());

    expect($queries[0])->toEndWith('order by "name" desc, "workers"."id" asc limit 5 offset 0');
});
