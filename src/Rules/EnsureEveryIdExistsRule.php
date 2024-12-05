<?php

namespace HassanDomeDenea\HddLaravelHelpers\Rules;

use Closure;
use HassanDomeDenea\HddLaravelHelpers\BaseModel;
use Illuminate\Contracts\Validation\ValidationRule;

class EnsureEveryIdExistsRule implements ValidationRule
{

    /**
     * @param class-string<BaseModel> $modelClass
     */
    public function __construct(public string $modelClass, public string $column = 'id')
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = $this->modelClass::whereIn($this->column, $value)->count();
        if ($count < count($value)) {
            $fail(__('Some of the provided items do not exist') . '.');
        }
    }
}
