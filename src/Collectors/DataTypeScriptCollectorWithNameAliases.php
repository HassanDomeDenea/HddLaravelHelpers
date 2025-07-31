<?php

namespace HassanDomeDenea\HddLaravelHelpers\Collectors;

use ReflectionAttribute;
use ReflectionClass;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Collectors\Collector;
use Spatie\TypeScriptTransformer\Structures\TransformedType;

class DataTypeScriptCollectorWithNameAliases extends Collector
{
    public function getTransformedType(ReflectionClass $class): ?TransformedType
    {
        if (!$class->isSubclassOf(BaseData::class)) {
            return null;
        }

        $transformer = new DataTypeScriptTransformer($this->config);

        return $transformer->transform($class, $this->reflectName($class->getAttributes()) ?: $class->getShortName());
    }

    private function reflectName(array $attributes): string|false
    {
        $nameAttributes = array_values(array_filter(
            $attributes,
            fn(ReflectionAttribute $attribute) => is_a($attribute->getName(), TypeScript::class, true)
        ));

        if (!empty($nameAttributes)) {
            /** @var TypeScript $nameAttribute */
            $nameAttribute = $nameAttributes[0]->newInstance();

            return $nameAttribute->name;
        }

        return false;
    }


}
