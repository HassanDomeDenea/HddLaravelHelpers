<?php

namespace HassanDomeDenea\HddLaravelHelpers\Database\Factories;

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition()
    {
        return [
            'number' => 'INV-' . $this->faker->unique()->numerify('###'),
            'date' => $this->faker->date(),
            'customer_name' => $this->faker->company(),
        ];
    }
}
