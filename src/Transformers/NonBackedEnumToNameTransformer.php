<?php

namespace HassanDomeDenea\HddLaravelHelpers\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class NonBackedEnumToNameTransformer implements Transformer
{

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return $value->name;
    }
}
