<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Spatie\LaravelData\Resource;

class AuditUserData extends Resource
{
    public function __construct(
        public int|string $id,
        public string     $name,
        public string     $username,
    )
    {
    }
}
