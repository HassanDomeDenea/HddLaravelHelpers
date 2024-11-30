<?php

namespace HassanDomeDenea\HddLaravelHelpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \HassanDomeDenea\HddLaravelHelpers\HddLaravelHelpers
 */
class HddLaravelHelpers extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HassanDomeDenea\HddLaravelHelpers\HddLaravelHelpers::class;
    }
}
