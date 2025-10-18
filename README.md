## Pulling

git clone --recurse-submodules https://HassanoDomeDenea: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@github.com/HassanDomeDenea/HddLaravelHelpers.git

# Tools to build standard model structure and conenct to primevue frontend library

#### This is a personal repository, containing tools I use commonly in my projects. It is under development, feel free to use anything here, in caution.

---

To use it in local projects, run the following command inside your main project:

```bash
composer config repositories.local '{"type": "path", "url": "../HddLaravelHelpers"}' --file composer.json
```

or add it manually to your composer.json file:

```
"repositories": {
    "local": {
        "type": "path",
        "url": "../HddLaravelHelpers"
    }
},
"minimum-stability": "dev",
```

Then run:

```bash
composer require HassanDomeDenea/HddLaravelHelpers
```

Or if the previous one didn't work:

```bash
composer require HassanDomeDenea/HddLaravelHelpers  @dev 
```

## Configurations:

- In config/data.php, update mapping into snake:

```php
    'name_mapping_strategy' => [
        'input' => null,
        'output' => \Spatie\LaravelData\Mappers\SnakeCaseMapper::class,
    ]
```

--- in config/typescript-transformer.php, use module writer:

```php
'writer' => Spatie\TypeScriptTransformer\Writers\ModuleWriter::class,
'output_file' => resource_path('js/types/laravel_generated.d.ts'),
'collectors' => [
        \HassanDomeDenea\HddLaravelHelpers\Collectors\DataTypeScriptCollectorWithNameAliases::class, #To Support Generics
        Spatie\TypeScriptTransformer\Collectors\DefaultCollector::class,
        Spatie\TypeScriptTransformer\Collectors\EnumCollector::class,
    ],
'transformers' => [
        Spatie\LaravelTypeScriptTransformer\Transformers\SpatieStateTransformer::class,
        Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class,
        Spatie\TypeScriptTransformer\Transformers\SpatieEnumTransformer::class,
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
        Spatie\LaravelTypeScriptTransformer\Transformers\DtoTransformer::class,
    ],
```

---
This package was developed by using [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
