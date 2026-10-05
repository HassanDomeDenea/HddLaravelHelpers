<?php

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\ResponseData;
use HassanDomeDenea\HddLaravelHelpers\Tests\Data\Worker\WorkerData;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Category;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Worker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;

/**
 * @param  array<string, mixed>  $options
 */
function workersDataTable(array $options = ['onlyRequestedColumns' => false]): DataTable
{
    return (new DataTable)
        ->setModel(Worker::query()->with('category'))
        ->setDataClass(WorkerData::class)
        ->setPayload(new Fluent([
            'page' => 1,
            'perPage' => -1,
            'options' => $options,
            'fields' => [['name' => 'name']],
            'sorts' => [['field' => 'id', 'direction' => 'asc']],
        ]));
}

/**
 * @return array<string, mixed>
 */
function asResponseJson(ResponseData $response): array
{
    return json_decode($response->toJson(), true);
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
    // More than one chunk, with a last chunk that is not a full one.
    Worker::insert(array_map(
        fn (int $number): array => ['name' => "Worker {$number}", 'category_id' => $category->id],
        range(1, 450),
    ));
});

it('returns the same rows as arrays as it does as data objects', function (): void {
    $asArrays = workersDataTable()->itemsAsArrays()->proceed();
    $asDataObjects = workersDataTable()->proceed();

    expect($asArrays->data)->toHaveCount(450)
        ->and($asArrays->data->first())->toBeArray()
        ->and($asArrays->data->first()['category'])->toBe(['id' => 1, 'name' => 'Electrical'])
        ->and($asDataObjects->data->first())->toBeInstanceOf(WorkerData::class)
        ->and(asResponseJson($asArrays))->toBe(asResponseJson($asDataObjects));
});

it('keeps to the requested columns when the rows are arrays', function (): void {
    $options = ['onlyRequestedColumns' => true, 'primaryKey' => 'id'];

    $asArrays = workersDataTable($options)->itemsAsArrays()->proceed();

    expect($asArrays->data->first())->toBe(['id' => 1, 'name' => 'Worker 1'])
        ->and(asResponseJson($asArrays))->toBe(asResponseJson(workersDataTable($options)->proceed()));
});

it('still hands data objects to an items modifier', function (): void {
    $received = null;

    workersDataTable()
        ->itemsAsArrays()
        ->modifyItemsCollection(function (Collection $items) use (&$received): Collection {
            $received = $items->first();

            return $items;
        })
        ->proceed();

    expect($received)->toBeInstanceOf(WorkerData::class);
});
