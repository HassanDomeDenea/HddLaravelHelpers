<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use HassanDomeDenea\HddLaravelHelpers\Data\ApiResponseData;
use Illuminate\Http\JsonResponse;

/**
 * @template TData
 */
class ApiResponse extends JsonResponse
{
    /**
     * @param TData $data
     * @param int $code
     * @return ApiResponse<TData>
     */
    public function success(mixed $data = null, int $code = 200): ApiResponse
    {
        return new self(new ApiResponseData(true, $data), $code);
    }

    public function fail($data = null, $code = 400): ApiResponse
    {
        return new self(new ApiResponseData(false, $data), $code);
    }

    public static function successResponse(mixed $data = null, int $code = 200): ApiResponse
    {
        return app(ApiResponse::class)->success($data, $code);
    }

    public static function failedResponse(mixed $data = null, int $code = 400): ApiResponse
    {
        return app(ApiResponse::class)->fail($data, $code);
    }

}
