<?php

namespace HassanDomeDenea\HddLaravelHelpers\Actions;

use Exception;
use HassanDomeDenea\HddLaravelHelpers\Data\BatchResponseData;
use HassanDomeDenea\HddLaravelHelpers\Data\BatchResponseItemData;
use HassanDomeDenea\HddLaravelHelpers\Requests\BatchRequestRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HandleBatchRequestAction
{
    public function handle(BatchRequestRequest $request): BatchResponseData
    {

        /** @var Collection<BatchResponseItemData> $responses */
        $responses = collect();
        $successfulRequests = 0;
        $failedRequests = 0;
        $requests = $request->array('requests');
        /** @var array{url:string, method:string, body:null|array<string,mixed>} $subRequest */
        foreach ($requests as $subRequest) {
            $method = mb_strtolower($subRequest['method']);
            $url = str($subRequest['url']);
            if (! $url->startsWith('/')) {
                $url = $url->prepend('/');
            }

            if (! $url->startsWith('/api')) {
                $url = $url->prepend('/api');
            }

            $payload = $subRequest['body'] ?? [];

            try {
                $response = app()->handle(Request::create($url->toString(), $method, $payload, $_COOKIE, $_FILES));

                $responses->add(new BatchResponseItemData(
                    $response->getStatusCode(),
                    $response instanceof JsonResponse ? $response->getData() : $response->getContent(),
                ));
                if ($response->isSuccessful()) {

                    $successfulRequests++;

                } else {
                    $failedRequests++;
                }
            } catch (Exception $exception) {
                $responses->add(new BatchResponseItemData(
                    500,
                    app()->isProduction() ? null : [
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode(),
                        'trace' => $exception->getTraceAsString(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ],
                ));
                $failedRequests++;
            }

        }

        return new BatchResponseData(
            $successfulRequests,
            $failedRequests,
            $successfulRequests === count($requests),
            $failedRequests === count($requests),
            $responses
        );
    }
}
