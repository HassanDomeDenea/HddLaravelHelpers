<?php

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Category;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Worker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;

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
    Worker::create(['name' => 'First', 'category_id' => $category->id]);
    Worker::create(['name' => 'Second', 'category_id' => $category->id]);
});

it('keeps the constraints of a relation the query already eager loads when a column needs it', function (string $columnRelation): void {
    $response = (new DataTable)
        ->setModel(Worker::query()->with(['category' => fn ($query) => $query->withCount('workers')]))
        ->setPayload(new Fluent([
            'page' => 1,
            'perPage' => -1,
            'fields' => [['name' => 'name'], ['name' => 'name', 'relation' => $columnRelation]],
            'sorts' => [['field' => 'id', 'direction' => 'asc']],
        ]))
        ->proceed();

    expect($response->data->first()->category->workers_count)->toBe(2);
})->with([
    'the relation itself' => 'category',
    'a relation nested under it' => 'category.workers',
]);
