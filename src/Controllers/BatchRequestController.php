<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Controllers;

use Exception;
use HassanDomeDenea\HddLaravelHelpers\Actions\HandleBatchRequestAction;
use HassanDomeDenea\HddLaravelHelpers\Attributes\RequestBodyAttribute;
use HassanDomeDenea\HddLaravelHelpers\Attributes\ResponseAttribute;
use HassanDomeDenea\HddLaravelHelpers\Data\BatchResponseData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiJsonResponse;
use HassanDomeDenea\HddLaravelHelpers\Requests\BatchRequestRequest;

use Illuminate\Routing\ResponseFactory;

final class BatchRequestController
{
    /**
     * @return ApiJsonResponse<BatchResponseData>
     */
    public function __invoke(BatchRequestRequest $request, HandleBatchRequestAction $action): ApiJsonResponse
    {
        $responses = $action->handle($request);

        return apiResponse()->success($responses);
    }
}
