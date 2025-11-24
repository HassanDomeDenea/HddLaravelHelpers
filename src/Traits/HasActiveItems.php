<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasActiveItems
{

    protected string $isActiveColumn = 'is_active';
    protected bool $defaultIsActiveValue = true;

    protected function initializeHasActiveItems(): void
    {
        $this->mergeCasts([
            $this->isActiveColumn => 'boolean',
        ]);
    }

    protected static function bootHasActiveItems(): void
    {
        static::creating(function ($model) {
            $model->{$model->isActiveColumn} = $model->{$model->isActiveColumn} ?? $model->defaultIsActiveValue;
        });
    }

    protected function disabled(): Attribute
    {
        return Attribute::get(
            fn($value, array $attributes) => !($attributes[$this->isActiveColumn] ?? $this->defaultIsActiveValue)
        );
    }
}
