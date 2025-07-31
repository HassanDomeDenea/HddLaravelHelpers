<?php

namespace HassanDomeDenea\HddLaravelHelpers\Database\Factories;

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Invoice;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition()
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'price' => $this->faker->randomFloat(2, 100, 1000),
        ];
    }
}
