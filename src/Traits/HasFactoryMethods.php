<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

trait HasFactoryMethods
{
    public static function fakeRandomOrNew(int $chanceOfTryingExistingModel = 50, ?string $specificProperty = null)
    {
        $tryExistingModel = fake()->boolean($chanceOfTryingExistingModel);
        if ($tryExistingModel) {
            if($specificProperty){
                return static::inRandomOrder()->first()?->{$specificProperty} ?: static::factory()->createOne()->{$specificProperty};
            }else{
                return static::inRandomOrder()->first()?->id ?: static::factory();
            }
        } else {
            if($specificProperty){
                return static::factory()->createOne()->{$specificProperty};
            }else{
                return static::factory();
            }
        }

    }
}
