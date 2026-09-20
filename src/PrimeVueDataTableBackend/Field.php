<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FieldType;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\SortAs;

class Field
{
    public string $columnName;
    public FieldType $source;
    public FieldType $sortSource;
    public FieldType $filterSource;
    public ?string $morphableTo;

    /**
     * How the sorted column's values are ordered, when ordering them as plain text is wrong.
     */
    public ?SortAs $sortAs;

    public function __construct(public string $name, public ?string $relation = null, ?string $source = 'main', ?string $filterSource = null, ?string $sortSource = null, public ?string $filterField = null, public ?string $sortField = null, ?string $morphableTo = null, ?string $sortAs = null)
    {
        $this->columnName = $this->name;
        $this->sortField ??= $this->name;
        $this->filterField ??= $this->name;
        if (filled($this->relation)) {
            $this->name = $this->relation . '.' . $this->name;
            $source = $filterSource = $sortSource = 'relation';
            $this->sortField = $this->relation.'.'.$this->sortField;
            $this->filterField = $this->relation.'.'.$this->filterField;
        }
        $this->source = FieldType::tryFrom($source);
        $this->filterSource = FieldType::tryFrom($filterSource ?: $source);
        $this->sortSource = FieldType::tryFrom($sortSource ?: $source);
        $this->morphableTo = $morphableTo;
        $this->sortAs = filled($sortAs) ? SortAs::tryFrom($sortAs) : null;
    }

    public function fullName(string $tableName): string
    {
        return $tableName . '.' . $this->name;
    }
}
