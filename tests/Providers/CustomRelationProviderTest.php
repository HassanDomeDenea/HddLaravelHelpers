<?php

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Invoice;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\InvoiceItem;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

// Run migrations for this test file only
beforeAll(function () {

});

// Clean up after tests
afterAll(function () {
    $invoiceItemsMigration = include __DIR__ . '/../Migrations/create_invoice_items_table.php';
    $invoiceItemsMigration->down();

    $invoicesMigration = include __DIR__ . '/../Migrations/create_invoices_table.php';
    $invoicesMigration->down();
});

beforeEach(function () {
    // Create test data using factories
    $invoice1 = Invoice::factory()->create([
        'number' => 'INV-001',
        'date' => '2023-01-01',
        'customer_name' => 'Customer 1',
    ]);

    $invoice2 = Invoice::factory()->create([
        'number' => 'INV-002',
        'date' => '2023-01-02',
        'customer_name' => 'Customer 2',
    ]);

    $invoice3 = Invoice::factory()->create([
        'number' => 'INV-003',
        'date' => '2023-01-03',
        'customer_name' => 'Customer 3',
    ]);

    // Create invoice items with different prices using factories
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice1->id,
        'description' => 'Item 1',
        'quantity' => 1,
        'price' => 100.00,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice1->id,
        'description' => 'Item 2',
        'quantity' => 2,
        'price' => 200.00,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice2->id,
        'description' => 'Item 3',
        'quantity' => 1,
        'price' => 300.00,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice3->id,
        'description' => 'Item 4',
        'quantity' => 1,
        'price' => 400.00,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice3->id,
        'description' => 'Item 5',
        'quantity' => 2,
        'price' => 500.00,
    ]);
});

it('can filter invoices by sum of item prices', function () {
    // Invoice 1 has items with prices 100 and 200, so sum is 300
    // Invoice 2 has an item with price 300, so sum is 300
    // Invoice 3 has items with prices 400 and 500, so sum is 900

    // Test whereRelationSum
    $invoices = Invoice::whereRelationSum('items', 'price', '>', 500)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-003');

    $invoices = Invoice::whereRelationSum('items', 'price', '=', 300)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-001');
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
});

it('can filter invoices by max of item prices', function () {
    // Invoice 1 has items with prices 100 and 200, so max is 200
    // Invoice 2 has an item with price 300, so max is 300
    // Invoice 3 has items with prices 400 and 500, so max is 500

    // Test whereRelationMax
    $invoices = Invoice::whereRelationMax('items', 'price', '>', 300)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-003');

    $invoices = Invoice::whereRelationMax('items', 'price', '<=', 300)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-001');
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
});

it('can filter invoices by min of item prices', function () {
    // Invoice 1 has items with prices 100 and 200, so min is 100
    // Invoice 2 has an item with price 300, so min is 300
    // Invoice 3 has items with prices 400 and 500, so min is 400

    // Test whereRelationMin
    $invoices = Invoice::whereRelationMin('items', 'price', '>=', 300)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
    expect($invoices->pluck('number')->toArray())->toContain('INV-003');

    $invoices = Invoice::whereRelationMin('items', 'price', '<', 200)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-001');
});

it('can filter invoices by avg of item prices', function () {
    // Invoice 1 has items with prices 100 and 200, so avg is 150
    // Invoice 2 has an item with price 300, so avg is 300
    // Invoice 3 has items with prices 400 and 500, so avg is 450

    // Test whereRelationAvg
    $invoices = Invoice::whereRelationAvg('items', 'price', '>', 200)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
    expect($invoices->pluck('number')->toArray())->toContain('INV-003');

    $invoices = Invoice::whereRelationAvg('items', 'price', '<', 200)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-001');
});

it('can filter invoices by count of items', function () {
    // Invoice 1 has 2 items
    // Invoice 2 has 1 item
    // Invoice 3 has 2 items

    // Test whereRelationCount
    $invoices = Invoice::whereRelationCount('items', '>', 1)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-001');
    expect($invoices->pluck('number')->toArray())->toContain('INV-003');

    $invoices = Invoice::whereRelationCount('items', '=', 1)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-002');
});

it('can filter invoices by sum of item prices using whereRelationSumIn', function () {
    // Invoice 1 has items with prices 100 and 200, so sum is 300
    // Invoice 2 has an item with price 300, so sum is 300
    // Invoice 3 has items with prices 400 and 500, so sum is 900

    // Test whereRelationSumIn
    $invoices = Invoice::whereRelationSum('items', 'price','in', [300, 900])->get();
    expect($invoices)->toHaveCount(3);

    $invoices = Invoice::whereRelationSum('items', 'price','in', [900])->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-003');
});

it('can filter invoices by sum of item prices using whereRelationSumNotIn', function () {
    // Invoice 1 has items with prices 100 and 200, so sum is 300
    // Invoice 2 has an item with price 300, so sum is 300
    // Invoice 3 has items with prices 400 and 500, so sum is 900

    // Test whereRelationSumNotIn
    $invoices = Invoice::withWhereRelationSum('items', 'price','notIn', [300])->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-003');

    $invoices = Invoice::whereRelationSum('items', 'price','notIn', [900])->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-001');
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
});

it('can use withSum and filter with having', function () {
    // Test withWhereRelationSum
    $invoices = Invoice::withWhereRelationSum('items', 'price', '>', 500)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-003');
    expect($invoices[0]->items_sum_price)->toBe(900);

    $invoices = Invoice::withWhereRelationSum('items', 'price', '=', 300)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-001');
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
    expect($invoices[0]->items_sum_price)->toBe(300);
    expect($invoices[1]->items_sum_price)->toBe(300);
});

it('can use withMax and filter with having', function () {
    // Test withWhereRelationMax
    $invoices = Invoice::withWhereRelationMax('items', 'price', '>', 300)->get();
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]->number)->toBe('INV-003');
    expect($invoices[0]->items_max_price)->toBe(500);

    $invoices = Invoice::withWhereRelationMax('items', 'price', '<=', 300)->get();
    expect($invoices)->toHaveCount(2);
    expect($invoices->pluck('number')->toArray())->toContain('INV-001');
    expect($invoices->pluck('number')->toArray())->toContain('INV-002');
});


it('can use withWhereAggregates', function () {

//    dd(Invoice::query()->withAggregate('items', 'price','sum')->ddRawSql()->toArray());
        expect(Invoice::withWhereAggregate('items', 'price', 'sum', '>', 400)->count())->toBe(1)
        ->and(Invoice::withWhereAggregateNot('items', 'price', 'sum', '>', 300)->count())->toBe(2)
        ->and(Invoice::where('id', 3)->withWhereAggregate('items', 'price', 'sum', '=', 900)->count())->toBe(1)
        ->and(Invoice::where('id', 2)->withWhereAggregateNot('items', 'price', 'sum', '=', 900)->count())->toBe(1)
        ->and(Invoice::where('id', 2)->orWithWhereAggregate('items', 'price', 'sum', '=', 900)->count())->toBe(2)
        ->and(Invoice::where('id', 2)->orWithWhereAggregateNot('items', 'price', 'sum', '<=', 900)->count())->toBe(1)
        ->and(Invoice::query()->whereAggregateIn('items', 'price', 'sum', [300, 900])->count())->toBe(3)
        ->and(Invoice::query()->whereAggregateNull('items', 'price', 'sum')->count())->toBe(0)
        ->and(Invoice::query()->whereAggregateNotNull('items', 'price', 'sum')->count())->toBe(3)
        ->and(Invoice::withWhereAggregateNot('items', 'price', 'sum', '>', 300)->first())->toHaveKey('items_sum_price', 300);


});
