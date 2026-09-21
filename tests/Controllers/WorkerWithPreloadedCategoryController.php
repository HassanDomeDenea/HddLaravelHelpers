<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Controllers;

use HassanDomeDenea\HddLaravelHelpers\Tests\Actions\CreateWorkerWithCategoryLoadedAction;

class WorkerWithPreloadedCategoryController extends WorkerController
{
    public ?string $createActionClass = CreateWorkerWithCategoryLoadedAction::class;
}
