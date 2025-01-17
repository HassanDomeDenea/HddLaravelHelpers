<?php

namespace HassanDomeDenea\HddLaravelHelpers\Rules;

use Closure;
use HassanDomeDenea\HddLaravelHelpers\BaseModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

class ModelExistsRule implements ValidationRule
{
    public Builder $query;

    /**
     * @param  callable(Builder):void  $callable
     */
    public function modifyQuery(callable $callable): self
    {
        $callable($this->query);

        return $this;
    }

    /**
     * @param  class-string<BaseModel>  $modelClass
     */
    public function __construct(private readonly string $modelClass, private readonly string $column = 'id')
    {
        $this->query = $this->modelClass::query();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->query->where($this->column,$value)->doesntExist()) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
