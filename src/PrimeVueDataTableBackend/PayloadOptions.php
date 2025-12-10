<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

class PayloadOptions
{
    public function __construct(
        public bool $onlyRequestedColumns = false,
        public ?string $primaryKey = null,
    )
    {
    }
}
