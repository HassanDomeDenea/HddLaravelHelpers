<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GroupedFilter
{
    public ?FilterMatchMode $matchMode;

    /**
     * @var Collection<Filter> | null
     */
    public ?Collection $constraints = null;

    public ?Filter $filter = null;

    public function __construct(
        public ?bool $isGroup = false,
        public ?string $operator = 'and',
        public ?string $column = null,
        ?string $matchMode = null,
        public mixed $value = null,
        ?array $constraints = null)
    {
        if ($operator !== 'or' && $operator !== 'and') {
            $this->operator = 'and';
        }

        if ($this->isGroup) {
            if ($constraints) {
                $this->constraints = collect(Arr::map($constraints, fn ($i) => new GroupedFilter(...$i)));
            }
        } else {
            if ($matchMode) {
                $this->matchMode = FilterMatchMode::tryFrom($matchMode) ?: FilterMatchMode::equals;
            }
            $this->filter = new Filter($this->column, false, $this->value, $matchMode, null, null);
        }

    }
}
