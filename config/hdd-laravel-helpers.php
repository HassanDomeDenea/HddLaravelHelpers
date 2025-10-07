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
    'whatsapp-userid'=>env('HDD_WHATSAPP_USER_ID'),
    'whatsapp-username'=>env('HDD_WHATSAPP_USERNAME'),
    'whatsapp-password'=>env('HDD_WHATSAPP_PASSWORD'),
    'whatsapp-hostname'=>env('HDD_WHATSAPP_HOSTNAME','whatsapp-bot.hdd-apps.com'),
    'whatsapp-protocol'=>env('HDD_WHATSAPP_PROTOCOL','https'),
    'data-classes'=>[
        'isolate-in-subfolders'=>env("HDD_DATA_CLASSES_ISOLATE_IN_SUBFOLDERS",true),
    ],
    'action-classes'=>[
        'isolate-in-subfolders'=>env("HDD_ACTION_CLASSES_ISOLATE_IN_SUBFOLDERS",true),
    ],
    'telegram'=>[
        'bot_token'=>env('HDD_TELEGRAM_BOT_TOKEN'),
        'backup_chat_id'=>env('HDD_TELEGRAM_BACKUP_CHAT_ID'),
        'errors_chat_id'=>env('HDD_TELEGRAM_ERRORS_CHAT_ID'),
    ]
];
