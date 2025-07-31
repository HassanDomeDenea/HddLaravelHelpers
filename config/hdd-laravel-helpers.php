<?php

// config for HassanDomeDenea/HddLaravelHelpers
return [
    'routes_types_methods_declarations' => false,
    'routes_types_ts_file_path' => "resources/js/composables/laravel-api.ts",
    'rotes_types_ts_eslint' => false,
    "axios-instance-import-path" => "./axios",
    "axios-instance-import-name" => "apiAxiosInstance",

    /**
     * This is the list of controller methods return types that are converted into (any) and ignored from import statements.
     *
     */
    "routes-ts-ignore-responses-types" => [
        'Response', 'JsonResponse'
    ],
    'hdd-laravel-helpers.with-where-aggregate-use-joins' => true,
];
