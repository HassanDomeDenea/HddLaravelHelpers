<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Stringable;

trait HasTranslatableAttributes
{
    private static bool $isSnakeCase = true;

    public static function getAttributeNames(): Collection
    {
        $reflection = new ReflectionClass(static::class);
        return collect($reflection->getProperties(ReflectionProperty::IS_PUBLIC))->map(fn($property) => $property->getName());
    }

    public static function getTranslatableAttributes(): array
    {
        return static::getAttributeNames()
            ->when(static::$isSnakeCase, fn(Collection $collection) => $collection->map(fn($propertyName)=> Str::snake($propertyName)))
            ->mapWithKeys(fn($propertyName) => [$propertyName => __(str($propertyName)->headline()->toString())])
            ->toArray();
    }

    public static function additionalAttributes(): array
    {
        return [];
    }

    public static function attributes(): array
    {
        return [
            ...static::getTranslatableAttributes(),
            ...static::additionalAttributes(),
        ];
    }
}
