<?php

namespace HassanDomeDenea\HddLaravelHelpers\Providers;

use HassanDomeDenea\HddLaravelHelpers\Services\YamlFileLoader;
use Illuminate\Translation\TranslationServiceProvider as IlluminateTranslationServiceProvider;

class YamlTranslationServiceProvider extends IlluminateTranslationServiceProvider
{
    /**
     * Register the translation line loader.
     * Add support for YAML files.
     */
    protected function registerLoader(): void
    {
        $this->app->singleton('translation.loader', function ($app) {

            return new YamlFileLoader($app['files'], $app['path.lang']);
        });
    }
}
