<?php

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Invoice;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\InvoiceItem;
use Illuminate\Support\Fluent;

/**
 * @param  array<string, mixed>  $payload
 */
function relationManyDataTable(array $payload): DataTable
{
    return (new DataTable)
        ->setModel(Invoice::class)
        ->setPayload(new Fluent([
            'page' => 1,
            'perPage' => -1,
            'options' => ['onlyRequestedColumns' => false, 'primaryKey' => 'id'],
            ...$payload,
        ]));
}

/**
 * @return array{0: Invoice, 1: Invoice}
 */
function invoicesWithItemDescriptions(): array
{
    $matching = Invoice::factory()->create();
    InvoiceItem::factory()->for($matching)->create(['description' => 'Steel widget']);
    $other = Invoice::factory()->create();
    InvoiceItem::factory()->for($other)->create(['description' => 'Wooden plank']);

    return [$matching, $other];
}

it('filters through a relation whose name is not its table name', function (string $filterField): void {
    [$matching] = invoicesWithItemDescriptions();

    $response = relationManyDataTable([
        'fields' => [['name' => 'items', 'filterField' => $filterField, 'source' => 'relation_many']],
        'groupedFilters' => [
            'operator' => 'and',
            'fields' => [['field' => $filterField, 'matchMode' => 'contains', 'value' => 'widget']],
        ],
    ])->proceed();

    expect($response->total)->toBe(1)
        ->and($response->data->pluck('id')->all())->toBe([$matching->id]);
})->with([
    'relation named after its method' => 'items.description',
    'relation named after its table' => 'invoice_items.description',
]);

it('searches a relation whose name is not its table name from the global filter', function (): void {
    [$matching] = invoicesWithItemDescriptions();

    $response = relationManyDataTable([
        'fields' => [
            ['name' => 'customer_name'],
            ['name' => 'items', 'filterField' => 'invoice_items.description', 'source' => 'relation_many'],
        ],
        'globalFilters' => ['customer_name', 'invoice_items.description'],
        'filters' => ['_global' => ['value' => 'widget', 'matchMode' => 'contains']],
    ])->proceed();

    expect($response->total)->toBe(1)
        ->and($response->data->pluck('id')->all())->toBe([$matching->id]);
});
