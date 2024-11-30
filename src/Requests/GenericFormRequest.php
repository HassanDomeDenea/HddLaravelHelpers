<?php

namespace HassanDomeDenea\HddLaravelHelpers\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class GenericFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function attributes(): array
    {
        if (method_exists($this, 'rules')) {
            $fields = array_keys($this->rules());

            return collect($fields)->mapWithKeys(function ($field) {
                if (trans()->has($field)) {
                    return [$field => __($field)];
                }

                return [];
            })->toArray();
        }

        return [];
    }
}
