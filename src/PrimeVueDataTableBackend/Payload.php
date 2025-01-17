<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FieldType;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Payload
{
    /**
     * @var Collection<Filter>
     */
    public Collection $filters;

    /**
     * @var Collection<Filter>
     */
    public Collection $fixedFilters;

    /**
     * @var Collection<Sort>
     */
    public Collection $sorts;

    public Collection $fields;

    public int $perPage;

    public int $first;

    public array $includes = [];

    public int $page;

    public Collection $globalFilters;

    public ?GroupedFilter $groupedFilter;

    /**
     * @throws ValidationException
     */
    public function __construct(array $payload)
    {
        $validated = $this->validate($payload);
        $this->filters = collect(Arr::map($validated['filters'] ?? [], fn ($i, $k) => new Filter($k, ...$i)));
        $this->fixedFilters = collect(Arr::map(($validated['fixedFilters'] ?? [] ?: []), function ($i, $k) {
            if (is_array($i)) {
                return new Filter($k, true, ...$i);
            } else {
                return new Filter($k, true, $i, 'equals');
            }
        }));
        $this->sorts = collect(Arr::map($validated['sorts'] ?? [], fn ($i) => new Sort(...$i)));
        $this->fields = collect(Arr::map($validated['fields'] ?? [], fn ($i) => new Field(...$i)));
        $this->perPage = ((int) ($validated['perPage'] ?? 25)) ?: 25;
        $this->includes = $validated['includes'] ?? [];
        $this->first = $this->perPage === -1 ? 0 : (int) ($validated['first'] ?? 0);
        $this->globalFilters = collect($validated['globalFilters'] ?? []);
        $this->groupedFilter = isset($validated['groupedFilters']) ? new GroupedFilter(...$validated['groupedFilters']) : null;
        if ($this->perPage === -1) {
            $this->page = 1;
        } else {
            $this->page = (int)round($this->first / $this->perPage,) + 1;
        }
    }

    /**
     * @throws ValidationException
     */
    public function validate($payload): array
    {
        $validator = Validator::make($payload, static::rules(), [
            //            '*' => __('Invalid Inputs'),
        ]);

        return $validator->validated();
    }

    public static function rules(): array
    {
        return [
            'first' => 'integer',
            'perPage' => 'integer',
            'globalFilters' => 'array',
            'globalFilters.*' => 'string',
            'includes' => 'array',
            'includes.*' => 'string',
            'filters' => 'array',
            'filters.*.matchMode' => [Rule::enum(FilterMatchMode::class)],
            'filters.*.value' => [],
            'filters.*.operator' => ['string'],
            'filters.*.constraints' => ['array'],
            'filters.*.constraints.*.value' => [''],
            'filters.*.constraints.*.matchMode' => [Rule::enum(FilterMatchMode::class)],
            'fixedFilters' => 'nullable|array',
            'fixedFilters.*' => '',
            'fixedFilters.*.matchMode' => [Rule::enum(FilterMatchMode::class)],
            'fixedFilters.*.value' => [],
            'fixedFilters.*.operator' => ['string'],
            'fixedFilters.*.constraints' => ['array'],
            'fixedFilters.*.constraints.*.value' => [''],
            'fixedFilters.*.constraints.*.matchMode' => [Rule::enum(FilterMatchMode::class)],
            'groupedFilters' => 'nullable|array',
            'groupedFilters.isGroup' => 'in:true,false',
            'groupedFilters.column' => 'string',
            'groupedFilters.value' => [],
            'groupedFilters.operator' => ['in:and,or'],
            'groupedFilters.constraints' => ['array'],
            'fields' => 'array',
            'fields.*.name' => ['required', 'string'],
            'fields.*.source' => ['required', Rule::enum(FieldType::class)],
            'fields.*.filterField' => ['required', 'string'],
            'fields.*.sortField' => ['required', 'string'],
            'sorts' => '',
            'sorts.*.field' => ['string'],
            'sorts.*.order' => 'integer|in:1,-1',
        ];
    }

    public static function GetRulesForAttributes(): array
    {
        return self::rules();
    }
}
