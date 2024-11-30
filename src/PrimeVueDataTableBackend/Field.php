<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FieldType;

class Field
{
    public FieldType $source;

    public function __construct(public string $name, string $source = 'main', public ?string $filterField = null, public ?string $sortField = null)
    {
        $this->source = FieldType::tryFrom($source);
        $this->sortField ??= $this->name;
        $this->filterField ??= $this->name;
    }

    public function fullName(string $tableName): string
    {
        return $tableName.'.'.$this->name;
    }
}
