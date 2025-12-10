<?php

use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use HassanDomeDenea\HddLaravelHelpers\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{media}/download/{file_name?}', [MediaController::class,'download'])->name('media.download');
Route::get('/media/{media}/{file_name?}', [MediaController::class,'show'])->name('media.show');

