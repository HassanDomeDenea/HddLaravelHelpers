<?php

use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/api/batch-request', BatchRequestController::class)->name('batch-request');
Route::post('/api/batch-request/{name}/{age?}', BatchRequestController::class)->name('batch-request2');
