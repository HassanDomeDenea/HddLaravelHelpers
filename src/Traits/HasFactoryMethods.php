<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

trait HasFactoryMethods
{
    public static function fakeRandomOrNew()
    {
        return static::inRandomOrder()->first()?->id ?: static::factory();
    }
}
