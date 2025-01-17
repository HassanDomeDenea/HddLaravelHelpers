<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Data\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class CastObjectField implements Cast
{
    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): object {
        return (object) $value;
    }
}
