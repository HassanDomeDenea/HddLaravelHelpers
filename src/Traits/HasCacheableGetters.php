<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use DateInterval;
use DateTimeInterface;
use Exception;
use HassanDomeDenea\HddLaravelHelpers\BaseModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

trait HasCacheableGetters
{

    private static function getHddModelCacheName(): string
    {
        $modelClassName = static::class;
        return "HddCacheableGettersCallbackNamesFor_{$modelClassName}";
    }

    private static function getHddCurrentCachedCallbackNames(): array
    {
        return Cache::get(static::getHddModelCacheName(), []);
    }

    private static function clearHddCachedNames(): void
    {
        try {
            $currentCachedCallbackNames = static::getHddCurrentCachedCallbackNames();
            foreach ($currentCachedCallbackNames as $name) {
                Cache::forget(static::getHddModelCacheName() . '_' . $name);
            }
            Cache::forget(static::getHddModelCacheName());
        } catch (Exception) {

        }
    }

    public static function getAndCache($name, $callback, null|DateInterval|DateTimeInterface $ttl = null): mixed
    {
        try {
            $currentCachedCallbackNames = static::getHddCurrentCachedCallbackNames();
            if (Arr::has($currentCachedCallbackNames, $name) === false) {
                $currentCachedCallbackNames[] = $name;
                Cache::rememberForever(static::getHddModelCacheName(), fn() => $currentCachedCallbackNames);
            }
        } catch (Exception) {

        }

        return Cache::remember(static::getHddModelCacheName() . '_' . $name, $ttl ?: now()->addMinutes(120), $callback);

    }

    public static function bootHasCacheableGetters()
    {
        static::saved(function ($model) {
            static::clearHddCachedNames();
        });
        static::deleted(function ($model) {
            static::clearHddCachedNames();
        });
        static::restored(function ($model) {
            static::clearHddCachedNames();
        });
    }
}
