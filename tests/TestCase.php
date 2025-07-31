<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests;

use HassanDomeDenea\HddLaravelHelpers\HddLaravelHelpersServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'HassanDomeDenea\\HddLaravelHelpers\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            HddLaravelHelpersServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // Run the package migrations
        /*
        $migration = include __DIR__.'/../database/migrations/create_hddlaravelhelpers_table.php.stub';
        $migration->up();
        */

        $invoicesMigration = include __DIR__.'/Migrations/create_invoices_table.php';
        $invoicesMigration->up();

        $invoiceItemsMigration = include __DIR__.'/Migrations/create_invoice_items_table.php';
        $invoiceItemsMigration->up();
    }
}
