<?php

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Invoice;
use Illuminate\Support\Fluent;

/**
 * @param  array<int, array<string, mixed>>  $fields
 */
function sortedInvoiceNumbers(array $fields, string $direction = 'asc'): array
{
    return (new DataTable)
        ->setModel(Invoice::class)
        ->setPayload(new Fluent([
            'page' => 1,
            'perPage' => -1,
            'options' => ['onlyRequestedColumns' => false, 'primaryKey' => 'id'],
            'fields' => $fields,
            'sorts' => [['field' => 'number', 'direction' => $direction]],
        ]))
        ->proceed()
        ->data
        ->pluck('number')
        ->all();
}

beforeEach(function (): void {
    foreach (['10', '2', '1'] as $number) {
        Invoice::factory()->create(['number' => $number]);
    }
});

it('orders a column of numbers kept as text by their value', function (): void {
    expect(sortedInvoiceNumbers([['name' => 'number', 'sortAs' => 'natural']]))
        ->toBe(['1', '2', '10'])
        ->and(sortedInvoiceNumbers([['name' => 'number', 'sortAs' => 'natural']], 'desc'))
        ->toBe(['10', '2', '1']);
});

it('leaves a column without the hint sorted as text', function (): void {
    expect(sortedInvoiceNumbers([['name' => 'number']]))->toBe(['1', '10', '2']);
});
