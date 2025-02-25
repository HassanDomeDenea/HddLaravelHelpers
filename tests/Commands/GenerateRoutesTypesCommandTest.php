<?php

use HassanDomeDenea\HddLaravelHelpers\Commands\GenerateRoutesTypesCommand;
use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use phpDocumentor\Reflection\DocBlockFactory;

it('generate routes types', function () {
    \Illuminate\Support\Facades\Storage::fake();
    Route::get('/api/posts/{post}', function ($post) {
        return $post;
    })->name('posts.show');
    \Pest\Laravel\artisan(GenerateRoutesTypesCommand::class);
});

