<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Controllers;

use HassanDomeDenea\HddLaravelHelpers\BaseCrudController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Data\Technician\TechnicianData;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Technician;
use HassanDomeDenea\HddLaravelHelpers\Tests\Policies\TechnicianPolicy;

class TechnicianController extends BaseCrudController
{
    public ?string $modelClass = Technician::class;

    public ?string $dataClass = TechnicianData::class;

    public ?string $policyClass = TechnicianPolicy::class;
}
