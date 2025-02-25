<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use HassanDomeDenea\HddLaravelHelpers\Data\ApiResponseData;
use Illuminate\Http\JsonResponse;

/**
 * @template TData
 */
class ApiJsonResponse extends JsonResponse
{
    /**
     * @param TData $data
     * @param int $code
     * @return ApiJsonResponse<TData>
     */
    public function success(mixed $data = null, int $code = 200): ApiJsonResponse
    {
        return new self(new ApiResponseData(true, $data), $code);
    }

    public function fail($data = null, $code = 400): ApiJsonResponse
    {
        return new self(new ApiResponseData(false, $data), $code);
    }

    public static function successResponse(mixed $data = null, int $code = 200): ApiJsonResponse
    {
        return app(ApiJsonResponse::class)->success($data, $code);
    }

    public static function failedResponse(mixed $data = null, int $code = 200): ApiJsonResponse
    {
        return app(ApiJsonResponse::class)->success($data, $code);
    }

}
