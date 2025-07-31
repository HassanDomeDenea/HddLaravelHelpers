<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Filter
{
    public FilterType $type;

    public ?FilterMatchMode $matchMode;

    /**
     * @var Collection<Filter> | null
     */
    public ?Collection $constraints = null;

    public function __construct(public $name = null, public bool $isFixedFilter = false, public $value = null, ?string $matchMode = null, public $operator = null, ?array $constraints = null)
    {
        if ($operator) {
            $this->type = FilterType::Multiple;
        } else {
            $this->type = FilterType::Single;
        }
        if ($constraints) {
            $this->constraints = collect(Arr::map($constraints, fn ($i) => new Filter($this->name, $this->isFixedFilter, ...$i)));
        }
        if ($matchMode) {
            $this->matchMode = FilterMatchMode::tryFrom($matchMode);
        }
    }

    public function isMultiple(): bool
    {
        return $this->type === FilterType::Multiple;
    }

    public function isEmpty(): bool
    {
        if ($this->isMultiple()) {
            foreach ($this->constraints as $constraint) {
                if (! $constraint->isEmpty()) {
                    return false;
                }
            }

            return true;
        } else {
            return $this->value === null || $this->value === '';
        }
    }

    public function isGlobal(): bool
    {
        return $this->name === '_global';
    }
}
