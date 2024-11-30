<?php

namespace HassanDomeDenea\HddLaravelHelpers;

use HassanDomeDenea\HddLaravelHelpers\Commands\HddLaravelHelpersCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HddLaravelHelpersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('hddlaravelhelpers')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_hddlaravelhelpers_table')
            ->hasCommand(HddLaravelHelpersCommand::class);
    }
}
