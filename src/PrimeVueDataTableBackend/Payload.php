<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FieldType;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Support\ValidatedInput;
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
    public ?GroupedFilter $fixedGroupedFilters;

    /**
     * @throws ValidationException
     */
    public function __construct(Fluent $payload)
    {
        $validated = $this->validate($payload->toArray());
        $this->filters = $validated->collect('filters')->map(fn($i, $k) => new Filter($k, ...$i));
        $this->fixedFilters = $validated->collect('fixedFilters')->map(function ($i, $k) {
            if (is_array($i)) {
                return new Filter($k, true, ...$i);
            } else {
                return new Filter($k, true, $i, 'equals');
            }
        });
        $this->sorts = $validated->collect('sorts')->map(fn($i) => new Sort(...$i));
        $this->fields = $validated->collect('fields')->map(fn($i) => new Field(...$i));
        $this->perPage = $validated->integer('perPage', 25);
        $this->includes = $validated->array('includes');
        $this->globalFilters = $validated->collect('globalFilters');
        $this->groupedFilter = $validated->has('groupedFilters') ? new GroupedFilter(...$validated->array('groupedFilters')) : null;
        $this->fixedGroupedFilters = $validated->has('fixedGroupedFilters') ? new GroupedFilter(...$validated->array('fixedGroupedFilters')) : null;
        $this->page = $this->perPage === -1 ? 1 : $validated->integer('page', 1);

        $this->first = ($this->page - 1) * $this->perPage;

    }

    /**
     * @throws ValidationException
     */
    public function validate($payload): ValidatedInput
    {
        $validator = Validator::make($payload, static::rules(), [
            //            '*' => __('Invalid Inputs'),
        ]);

        return $validator->safe();
    }

    public static function rules(): array
    {
        return [
            'first' => 'integer',
            'perPage' => 'integer',
            'page' => 'integer|gte:-1',
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
            'groupedFilters.fields' => ['array'],
            'groupedFilters.fields.*' => [function ($attribute, $value, $fail) {
                if (!Payload::isProperGroupedFiltersField($value)) {
                    $fail(__('Invalid parameters'));
                }
            }],
            'groupedFilters.operator' => ['in:and,or'],
            'fixedGroupedFilters' => 'nullable|array',
            'fixedGroupedFilters.fields' => ['array'],
            'fixedGroupedFilters.fields.*' => [function ($attribute, $value, $fail) {
                if (!Payload::isProperGroupedFiltersField($value)) {
                    $fail(__('Invalid parameters'));
                }
            }],
            'fixedGroupedFilters.operator' => ['in:and,or'],
            'fields' => 'array',
            'fields.*.name' => ['required', 'string'],
            'fields.*.relation' => ['nullable', 'string'],
            'fields.*.source' => ['required', Rule::enum(FieldType::class)],
            'fields.*.filterSource' => [Rule::enum(FieldType::class)],
            'fields.*.sortSource' => [Rule::enum(FieldType::class)],
            'fields.*.filterField' => ['required', 'string'],
            'fields.*.sortField' => ['required', 'string'],
            'fields.*.morphableTo' => ['nullable', 'string'],
            'sorts' => '',
            'sorts.*.field' => ['required', 'string'],
//            'sorts.*.order' => 'integer|in:1,-1',
            'sorts.*.direction' => ['required', 'in:asc,desc'],
        ];
    }

    public static function GetRulesForAttributes(): array
    {
        return self::rules();
    }

    /**
     * @param array $field
     * @return bool
     */
    public static function isProperGroupedFiltersField(array $field): bool
    {
        $data = fluent($field);

        if ($data->has('operator') && $data->has('fields')) {
            // Wrapper
            if (!in_array($data->string('operator'), ['and', 'or'])) {
                return false;
            }
            if (!is_array($data->get('fields'))) {
                return false;
            }

            foreach ($data->array('fields') as $i) {
                if (!Payload::isProperGroupedFiltersField($i)) {
                    return false;
                }
            }

            return true;
        } else {
            // Field
            if (!$data->has('field') || !$data->has('matchMode')) {
                return false;
            }
            if (!is_string($data->get('field'))) {
                return false;
            }
            if (FilterMatchMode::tryFrom($data->string('matchMode')) === null) {
                return false;
            }
            return true;
        }
    }
}
