<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Controllers;

use HassanDomeDenea\HddLaravelHelpers\BaseCrudController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Employee;
use HassanDomeDenea\HddLaravelHelpers\Tests\Policies\EmployeePolicy;

class EmployeeController extends BaseCrudController
{
    public ?string $modelClass = Employee::class;

    public ?string $policyClass = EmployeePolicy::class;
}
