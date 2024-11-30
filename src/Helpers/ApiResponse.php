<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success($data = null, $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $code);
    }

    public static function fail($data = null, $code = 422): JsonResponse
    {
        return response()->json(['success' => false, 'data' => $data], $code);
    }
}
