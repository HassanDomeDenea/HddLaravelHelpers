<?php

declare(strict_types=1);

use HassanDomeDenea\HddLaravelHelpers\Facades\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiJsonResponse;

if (! function_exists('apiResponse')) {
    /** @return ApiJsonResponse */
    function apiResponse(): ApiJsonResponse
    {

        return app(ApiJsonResponse::class);
    }
}

