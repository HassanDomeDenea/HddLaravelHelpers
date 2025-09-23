<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

trait HasFactoryMethods
{
    public static function fakeRandomOrNew(int $chanceOfTryingExistingModel = 50)
    {
        $tryExistingModel = fake()->boolean($chanceOfTryingExistingModel);
        if ($tryExistingModel) {
            return static::inRandomOrder()->first()?->id ?: static::factory();
        } else {
            return static::factory();
        }
    }
}
