<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Data\Casts;

use HassanDomeDenea\HddLaravelHelpers\Data\TranslationNameData;
use Illuminate\Support\Str;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class CastTranslatorField implements Cast
{
    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): mixed {
        if (Str::endsWith($property->name, 'Translations')) {

            return TranslationNameData::from($context->mappedProperties[$property->name] ?? []);
        }
        $context->mappedProperties[$property->name.'Translations'] = $value;

        return $value[app()->getLocale()] ?? $value[array_key_first($value)] ?? null;

    }
}
