<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GroupedFilter
{
    public ?FilterMatchMode $matchMode;

    /**
     * @var Collection<GroupedFilter> | null
     */
    public ?Collection $fields = null;

    public ?Filter $filter = null;

    public ?bool $isGroup = false;

    public function __construct(

        public ?string $operator = 'and',
        public ?string $field = null,
        ?string        $matchMode = null,
        public mixed   $value = null,
        ?array         $fields = null,
    )
    {
        if ($operator !== 'or' && $operator !== 'and') {
            $this->operator = 'and';
        }

        if (empty($fields) && !empty($this->field)) {
            $this->isGroup = false;
            if ($matchMode) {
                $this->matchMode = FilterMatchMode::tryFrom($matchMode) ?: FilterMatchMode::equals;
            }
            $this->filter = new Filter($this->field, false, $this->value, $matchMode, null, null);
        } else {
            $this->isGroup = true;

            $this->fields = collect(Arr::map($fields ?: [], fn($i) => new GroupedFilter(...Arr::only($i, ['field', 'matchMode', 'value', 'fields']), ...(empty($i['operator']) ? ['operator' => $this->operator] : []))));
        }

    }
}
