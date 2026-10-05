<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Data\Technician;

use Spatie\LaravelData\Data;

class TechnicianData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
