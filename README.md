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
```
Then run:

```bash
composer require HassanDomeDenea/HddLaravelHelpers
```

Or if the previous one didn't work:

```bash
composer require HassanDomeDenea/HddLaravelHelpers  @dev 
```

---
This package was developed by using [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
