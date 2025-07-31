<?php

namespace HassanDomeDenea\HddLaravelHelpers\Collectors;

use HassanDomeDenea\HddLaravelHelpers\Transformers\FormRequestTransformer;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Collectors\Collector;
use Spatie\TypeScriptTransformer\Structures\TransformedType;

class FormRequestTypeScriptCollector extends Collector
{
    public function getTransformedType(ReflectionClass $class): TransformedType|null
    {
        if (! $class->isSubclassOf(FormRequest::class)) {
            return null;
        }

        $transformer = new FormRequestTransformer;

        return $transformer->transform($class, $class->getShortName());
    }
}
