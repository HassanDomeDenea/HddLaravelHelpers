<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Controllers;

class ConstrainedWorkerController extends WorkerController
{
    protected function getEagerLoads(): array
    {
        return [
            'category',
            'tags' => fn ($query) => $query->where('name', 'keep'),
        ];
    }
}
