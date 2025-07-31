<?php

namespace HassanDomeDenea\HddLaravelHelpers\Transformers;

use HassanDomeDenea\HddLaravelHelpers\Helpers\AppendableString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\RequiredIf;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Throwable;

class FormRequestTransformer implements Transformer
{
    public function transform(ReflectionClass $class, string $name): TransformedType|null
    {
        if (!is_subclass_of($class->name, FormRequest::class)) {
            return null;
        }

        $typescriptProperties = [];
        $rules = [];
        try {
            if ($class->isInstantiable() && $class->hasMethod('rules') && $class->getConstructor()?->isPublic()) {
                $formRequestClassInstance = $class->newInstance();
                if (method_exists($formRequestClassInstance, 'rules')) {
                    $rules = $formRequestClassInstance->rules();
                }
            }
        }catch (Throwable $e){
            $publicProperties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
            $ownProperties = array_filter($publicProperties, function ($prop) use ($class) {
                return $prop->getDeclaringClass()->getName() === $class->getName();
            });

            foreach ($ownProperties as $property){
                $typescriptProperties[] = $property->getName().": any";
            }

        }
        $rulesCollection=collect($rules);
        foreach ($rules as $ruleName => $ruleData) {
            if (Str::contains($ruleName, ['*', '.'])) {
                continue;
            }
            $result = $this->getTypesFromRules($ruleData, $rulesCollection, $ruleName);

            if ($result['isRequired'] === false) {
                $ruleName = $ruleName . "?";
            }
            $ruleName .= ": ";

            $ruleName .= join(' & ', $result['types']);

            $typescriptProperties[] = $ruleName;
        }
        return TransformedType::create(
            $class,
            $name,
            "{\n" . implode("\n", $typescriptProperties) . "\n}",
        );
    }

    /**
     * @return array{isRequired: true|false, types: string[]}
     */
    private function getTypesFromRules(array|string $rules, Collection $allRules, $name): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        $isRequired = false;
        $types = [];
        foreach ($rules as $rule) {
            if (is_object($rule)) {
                $rule = class_basename($rule);
            }
            switch ($rule) {
                case 'required':
                case RequiredIf::class:
                    $isRequired = true;
                    break;
                case 'string':
                    $types[] = 'string';
                    break;
                case 'integer':
                case 'decimal':
                case 'float':
                case 'number':
                case 'numeric':
                    $types[] = 'number';
                    break;
                case 'boolean':
                    $types[] = 'boolean';
                    break;
                case 'nullable':
                    $types[] = 'null';
                    break;
                case 'array':

                    $ruleSubRules = $allRules->where(function ($_, $rule2) use ($name) {
                        return Str::startsWith($rule2, $name . '.*.');
                    });
                    if ($ruleSubRules->isNotEmpty()) {
                        $str = new AppendableString("{ ");
                        $str->append($ruleSubRules->map(function ($ruleSubRule, $ruleSubRuleKey) use ($name, &$isRequired, $rules) {
                            $definitions = $this->getTypesFromRules($ruleSubRule, collect($rules), $ruleSubRuleKey);
                            if ($definitions['isRequired'] === true) {
                                $isRequired = true;
                            } else {
                                $ruleSubRuleKey .= "?";
                            }
                            return str(Str::after($ruleSubRuleKey, $name . '.*.'))->append(": ")->append(join('& ', $definitions['types']))->toString();

                        })->join(", "));
                        $str->append(" }");
                        $types[] = $str->toString() . '[]';
                    } else {
                        $ruleFlatSubRules = $allRules->firstWhere(function ($_, $rule2) use ($name) {
                            return $rule2 === $name . '.*';
                        });

                        if ($ruleFlatSubRules) {
                            $str = new AppendableString("(");

                            $definitions = $this->getTypesFromRules($ruleFlatSubRules, collect($rules), ".*");
                            if ($definitions['isRequired'] === true) {
                                $isRequired = true;
                            }
                            $str->append(join(' & ', $definitions['types']));

                            $str->append(")");
                            $types[] = $str->toString() . '[]';
                        } else {
                            $types[] = 'any[]';
                        }

                    }
                    break;
                case 'object':
                    $types[] = "{ [p in string]: any }";
                    break;
            }
        }

        if (empty($types)) {
            $types = ['any'];
        }

        return ['isRequired' => $isRequired, 'types' => $types,];
    }
}
