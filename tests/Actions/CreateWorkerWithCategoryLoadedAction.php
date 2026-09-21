<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Actions;

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Worker;

class CreateWorkerWithCategoryLoadedAction
{
    public function handle(array $attributes): Worker
    {
        $worker = Worker::create($attributes);
        $worker->load('category');

        return $worker;
    }
}
