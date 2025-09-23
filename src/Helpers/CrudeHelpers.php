<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use HassanDomeDenea\HddLaravelHelpers\Data\BaseData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;

class CrudeHelpers
{
    /**
     * @param class-string<FormRequest> $formRequestClassName
     * @param mixed $data
     * @param array<string,mixed> $formRequestExtraBindings
     * @return array|null
     */
    public static function getAttributesFromFormRequest(string $formRequestClassName, mixed $data,array $formRequestExtraBindings=[]): array|null
    {
        if (class_exists($formRequestClassName) && is_subclass_of($formRequestClassName, FormRequest::class)) {
            $formRequestInstance = new $formRequestClassName(request: $data);
            $formRequestInstance->merge($data);
            foreach ($formRequestExtraBindings as $modelBindingName => $modelBindingValue){
                $formRequestInstance->{$modelBindingName} = $modelBindingValue;
            }
            if (method_exists($formRequestInstance, 'authorize')) {
                abort_unless($formRequestInstance->authorize(), 403);
            }
            if (method_exists($formRequestInstance, 'rules')) {
                $rules = $formRequestInstance->rules();
            } else {
                $rules = [];
            }
            $validator = Validator::make($data, $rules,
                $formRequestInstance->messages(), $formRequestInstance->attributes());
            $validator->validate();
            return $validator->validated();
        }
        return null;
    }

    /**
     * @param string $dataClassName
     * @param mixed $data
     * @param $expectedType
     * @return Data|Fluent|array|null
     */
    public static function getAttributesFromDataClass(string $dataClassName, mixed $data, $expectedType = null): Data|Fluent|array|null
    {
        if (class_exists($dataClassName) && is_subclass_of($dataClassName, Data::class)) {
            if (method_exists($dataClassName, 'authorize')) {
                $validationContext = new ValidationContext(
                    $payload = $data,
                    $payload,
                    new ValidationPath()
                );
                abort_unless((bool)app()->call([$dataClassName, 'authorize'], ['context' => $validationContext]), 403);
            }
            if ($dataClassName === $expectedType) {
                $attributes = $dataClassName::validateAndCreate($data);
            } else {
                $attributes = $dataClassName::validate($data);
                if ($expectedType === Fluent::class) {
                    $attributes = new Fluent($attributes);
                }
            }
            return $attributes;
        }

        return null;
    }

    /**
     * @param class-string<FormRequest>|null $formRequestClassName
     * @param class-string<Data>|null $dataClassName
     * @param mixed $data
     * @param class-string<Data|Fluent>|null $expectedType
     * @param array $formRequestExtraBindings
     * @return Data|Fluent|array|null
     */
    public static function getAttributesFromAnything(?string $formRequestClassName = null, ?string $dataClassName = null, mixed $data = [], ?string $expectedType = null,array $formRequestExtraBindings= []): Data|Fluent|array|null
    {
        if($formRequestClassName && $dataClassName) {
            return self::getAttributesFromFormRequest($formRequestClassName, $data,$formRequestExtraBindings);
        } else if($dataClassName) {
            return self::getAttributesFromDataClass($dataClassName, $data, $expectedType);
        } else {
            return null;
        }
    }
}
