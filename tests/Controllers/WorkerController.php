<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Controllers;

use HassanDomeDenea\HddLaravelHelpers\BaseCrudController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Data\Worker\WorkerData;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Worker;
use Spatie\QueryBuilder\QueryBuilder;

class WorkerController extends BaseCrudController
{
    public ?string $modelClass = Worker::class;

    public ?string $dataClass = WorkerData::class;

    public ?string $policyClass = '';

    protected array $with = ['category'];

    protected array $allowedIncludes = ['tags'];

    public function queryBuilder(): QueryBuilder
    {
        return $this->getQueryBuilder();
    }
}
