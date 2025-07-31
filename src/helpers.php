<?php

declare(strict_types=1);

use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use Spatie\LaravelData\Optional;

if (! function_exists('apiResponse')) {
    /** @return ApiResponse */
    function apiResponse(): ApiResponse
    {

        return app(ApiResponse::class);
    }
}


if (! function_exists('filledOptional')) {
    /**
     * Determine if a value is "filled" and not Data Optional.
     *
     * @phpstan-assert-if-true !=null|'' $value
     *
     * @phpstan-assert-if-false !=numeric|bool $value
     *
     * @param  mixed  $value
     * @return bool
     */
    function filledOptional(mixed $value): bool
    {
        if($value instanceof Optional){
            return  false;
        }
        return filled($value);
    }
}

