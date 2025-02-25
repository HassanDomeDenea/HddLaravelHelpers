<?php

use HassanDomeDenea\HddLaravelHelpers\Data\BatchResponseData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

it('can handle batch request', function () {
    Route::get('/api/test', fn() => 'test');
    $request = new \HassanDomeDenea\HddLaravelHelpers\Requests\BatchRequestRequest([
        'requests' => [
            [
                'method' => 'GET',
                'url' => 'test',
            ],
        ],
    ]);
    $result = app(\HassanDomeDenea\HddLaravelHelpers\Actions\HandleBatchRequestAction::class)->handle($request);
    expect($result)->toBeInstanceOf(BatchResponseData::class)
        ->and($result->failedRequests)->toBe(0)
        ->and($result->successRequests)->toBe(1)
        ->and($result->allAreSuccessful)->toBeTrue()
        ->and($result->allAreFailed)->toBeFalse()
        ->and($result->responses)->toHaveCount(1)
        ->and($result->responses->first()->status_code)->toBe(200)
        ->and($result->responses->first()->content)->toBe('test');
});


it('can handle bad request', function () {
    $this->withoutExceptionHandling();
    Route::get('/api/test', function () {
        return response('', 400);
    })->middleware(\HassanDomeDenea\HddLaravelHelpers\Tests\TestHelpers\BadMiddleware::class);
    $request = new \HassanDomeDenea\HddLaravelHelpers\Requests\BatchRequestRequest([
        'requests' => [
            [
                'method' => 'get',
                'url' => 'test',
            ],
        ],
    ]);
    $result = app(\HassanDomeDenea\HddLaravelHelpers\Actions\HandleBatchRequestAction::class)->handle($request);
    expect($result)->toBeInstanceOf(BatchResponseData::class)
        ->and($result->failedRequests)->toBe(1)
        ->and($result->successRequests)->toBe(0)
        ->and($result->allAreSuccessful)->toBeFalse()
        ->and($result->allAreFailed)->toBeTrue()
        ->and($result->responses)->toHaveCount(1)
        ->and($result->responses->first()->status_code)->toBe(500)
        ->and($result->responses->first()->content)->toHaveKey('message','Bad Middleware');
});
