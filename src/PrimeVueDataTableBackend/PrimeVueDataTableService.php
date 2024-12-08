<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use Closure;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FieldType;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

use function request;

class PrimeVueDataTableService
{
    private ?Payload $payload = null;

    private ?Builder $query = null;

    protected array $joins = [];

    protected array $_addedSelects = [];

    protected array $relations = [];

    protected array $relationsCounts = [];

    protected ?string $mainTableName = null;

    protected ?Closure $_itemsModifier = null;

    /**
     * @var Data::class | null
     */
    protected mixed $_dataClass = null;

    public function __construct() {}

    public function setPayload($data = null): self
    {
        $this->payload = new Payload($data ?: request()->all());

        return $this;
    }

    /**
     * @param  class-string<Model>|Builder  $modelName
     * @param  Data::class  $dataClassName
     * @return $this
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function setModel(string|Builder $modelName, ?string $dataClassName = null): self
    {
        if (is_string($modelName)) {
            /** @var Model::class $modelName */
            $this->query = $modelName::query();
            $this->mainTableName = $modelName::getTableName();
        } else {
            /** @var Builder $modelName */
            $this->query = $modelName;
            $this->mainTableName = $modelName->getModel()->getTable();

        }
        if ($dataClassName) {
            $this->_dataClass = $dataClassName;
        }

        return $this;
    }

    public function addSelect(Expression $expression): self
    {
        $this->_addedSelects[] = $expression;

        return $this;
    }

    public function joinRelation(string|array $relationName): self
    {
        if (is_array($relationName)) {
            foreach ($relationName as $name) {
                if (Arr::has($this->joins, $name)) {
                    continue;
                }
                $this->joins[] = $name;
            }
        } else {
            if (! Arr::has($this->joins, $relationName)) {
                $this->joins[] = $relationName;
            }
        }

        return $this;
    }

    public function loadRelation(string|array $relationName): self
    {
        if (is_array($relationName)) {
            foreach ($relationName as $name) {
                if (Arr::has($this->relations, $name)) {
                    continue;
                }
                $this->relations[] = $name;
            }
        } else {
            if (! Arr::has($this->relations, $relationName)) {
                $this->relations[] = $relationName;
            }
        }

        return $this;
    }

    public function loadRelationCount(string|array $relationName): self
    {
        if (is_array($relationName)) {
            foreach ($relationName as $name) {
                if (Arr::has($this->relationsCounts, $name)) {
                    continue;
                }
                $this->relationsCounts[] = $name;
            }
        } else {
            if (! Arr::has($this->relationsCounts, $relationName)) {
                $this->relationsCounts[] = $relationName;
            }
        }

        return $this;
    }

    private function processJoinsAndRelations(Builder &$query): void
    {
        foreach (array_unique($this->joins) as $join) {
            $joinName = $join;
            if (Str::contains($joinName, '.')) {
                $joinNamesList = explode('.', $joinName);
                $joinNamesAliases = [];
                foreach ($joinNamesList as $joinNameAlias) {
                    $joinNamesAliases[$joinNameAlias] = fn ($join2) => $join2->as($joinNameAlias);
                }
                $query->leftJoinRelationship($join, $joinNamesAliases);
            } else {
                $query->leftJoinRelationshipUsingAlias($join, $joinName);
            }
        }
        if (! empty($this->relations)) {
            $query->with(array_unique($this->relations));
        }
        if (! empty($this->relationsCounts)) {

            $query->withCount(array_unique($this->relationsCounts));
        }
    }

    private function processAddedCustomColumns(): void
    {

        foreach ($this->_addedSelects as $addedSelect) {
            $this->query->addSelect($addedSelect);
        }
    }

    /**
     * @param  Closure<Builder>  $closure
     * @return $this
     */
    public function modifyQuery(Closure $closure): self
    {
        $closure($this->query);

        return $this;
    }

    /**
     * @param  Closure<Builder>  $closure
     * @return $this
     */
    public function getQuery(Closure $closure): Builder
    {
        return $this->query->clone();
    }

    public function checkNestedColumnName(string $column): void
    {
        if (Str::contains($column, '.')) {
            $field = $this->payload->fields->where('filterField', $column)->first();

            if (! in_array($field?->source->value, [FieldType::json->value, FieldType::relationMany->value])) {
                $this->joinRelation(Str::beforeLast($column, '.'));
            }
        }
    }

    public function modifyColumnName(&$columnName, $sortOrFilterColumnName = 'name'): string
    {
        /** @var Field $field */
        $field = $this->payload->fields->where($sortOrFilterColumnName, $columnName)->first();

        if (! Str::contains($columnName, '.')) {

            if (! $this->payload->fields->where('name', $columnName)->whereIn('source', [FieldType::relationCount, FieldType::jsonArray, FieldType::relationMany])->first()) {
                $columnName = $this->mainTableName.'.'.$columnName;
            }
        } elseif ($field?->source->value === FieldType::json->value) {

            $columnName = Str::replace('.', '->', $field->filterField);
        } elseif (substr_count($columnName, '.') > 1) {
            $relationName = Str::afterLast(Str::beforeLast($columnName, '.'), '.');
            $columnName = $relationName.Str::after($columnName, $relationName);
        }

        return $columnName;
    }

    private function addFilterToField($columnName, $value, FilterMatchMode $matchMode = FilterMatchMode::contains, ?Builder $query = null, $method = 'where', $skipChecking = false): void
    {
        $originalColumnName = $columnName;
        $nameOfValueParameter = 'value';
        $isRelationCountField = false;
        $isRelationManyField = false;
        $originalMethod = $method;

        if (! $skipChecking) {
            $this->checkNestedColumnName($columnName);
            $this->modifyColumnName($columnName, 'filterField');
            if ($this->payload->fields->where('name', $columnName)->where('source', FieldType::relationCount)->first()) {
                $method .= 'Has';
                $columnName = Str::beforeLast($columnName, '_count');
                $nameOfValueParameter = 'count';
                $isRelationCountField = true;
            } elseif ($this->payload->fields->where('name', $columnName)->where('source', FieldType::relationMany)->first()) {
                $method .= 'Has';
                $isRelationManyField = Str::beforeLast($columnName, '.');
                $columnName = Str::afterLast($columnName, '.');
            }
        }

        $operator = null;
        $onlyColumnName = false;
        if ($isRelationManyField) {
            ($query ?: $this->query)->{$method}($isRelationManyField, function ($q) use (

                $isRelationManyField,
                $matchMode, $value, $columnName
            ) {
                //                $q->where($columnName,$value);
                $this->addFilterToField($isRelationManyField.'.'.$columnName, $value, $matchMode, $q, 'where', true);
            });

            return;
        }

        switch ($matchMode) {
            case FilterMatchMode::contains:
                $operator = 'LIKE';
                $value = '%'.$value.'%';
                break;
            case FilterMatchMode::containsAll:
                $operator = 'closure';
                $value = explode(' ', preg_replace('!\s+!', ' ', $value));
                break;
            case FilterMatchMode::isNull:
                $operator = '=';
                $method .= 'Null';
                $onlyColumnName = true;
                break;
            case FilterMatchMode::isNotNull:
                $operator = '!=';
                $method .= 'NotNull';
                $onlyColumnName = true;
                break;
            case FilterMatchMode::notContains:
                $operator = 'NOT LIKE';
                $value = '%'.$value.'%';
                break;
            case FilterMatchMode::startsWith:
                $operator = 'LIKE';
                $value = $value.'%';
                break;
            case FilterMatchMode::endsWith:
                $operator = 'LIKE';
                $value = '%'.$value;
                break;
            case FilterMatchMode::equals:
                $operator = '=';
                break;
            case FilterMatchMode::notEquals:
                $operator = '!=';
                break;
            case FilterMatchMode::dateIs:
                $operator = '=';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateBefore:
                $operator = '<';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateAfter:
                $operator = '>';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateIsOrBefore:
                $operator = '<=';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateIsOrAfter:
                $operator = '>=';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateIsNot:
                $operator = '!=';
                $method .= 'Date';
                break;
            case FilterMatchMode::whereIn:
                $nameOfValueParameter = 'values';
                $method .= 'In';
                break;
            case FilterMatchMode::whereNotIn:
                $nameOfValueParameter = 'values';
                $method .= 'NotIn';
                break;
            case FilterMatchMode::lessThan:
                $operator = '<';
                break;
            case FilterMatchMode::lessThanOrEquals:
                $operator = '<=';
                break;
            case FilterMatchMode::greaterThan:
                $operator = '>';
                break;
            case FilterMatchMode::greaterThanOrEquals:
                $operator = '>=';
                break;
        }

        if ($onlyColumnName || ($value !== null)) {
            if ($this->payload->fields->where('name', $columnName)->where('source', FieldType::jsonArray)->first()
                && $matchMode === FilterMatchMode::whereIn) {
                ($query ?: $this->query)->{$originalMethod}(function ($q) use ($columnName, $value) {
                    foreach ($value as $v) {
                        $q->whereJsonContains($columnName, $v);
                    }
                });
                $columnName = Str::beforeLast($columnName, '_count');
            } elseif ($operator === 'closure') {
                ($query ?: $this->query)->{$originalMethod}(function ($q) use (
                    $isRelationCountField,
                    $nameOfValueParameter, $method, $value, $columnName
                ) {
                    foreach ($value as $v) {
                        if (! $isRelationCountField) {
                            $params['operator'] = 'LIKE';
                            $params[$nameOfValueParameter] = "%$v%";
                        } else {
                            $params['operator'] = '=';
                            $params[$nameOfValueParameter] = $v;
                        }
                        $q->{$method}($columnName, ...$params);
                    }
                });
            } else {
                $params = [];
                if (! $onlyColumnName) {
                    if ($operator) {
                        $params['operator'] = $operator;
                    }
                    $params[$nameOfValueParameter] = $value;
                }
                ($query ?: $this->query)->{$method}($columnName, ...$params);
            }

        }
    }

    private function processFilters(): bool
    {

        $hasFilters = false;
        foreach ($this->payload->filters as $filter) {
            if ($filter->isEmpty()) {
                continue;
            }
            $hasFilters = true;

            if ($filter->isGlobal()) {
                $this->query->where(function (Builder $query) use ($filter) {
                    $firstWhereStatement = true;
                    foreach ($this->payload->globalFilters as $columnName) {

                        $this->addFilterToField($columnName, $filter->value, $filter->matchMode, query: $query, method: $firstWhereStatement ? 'where' : 'orWhere');
                        $firstWhereStatement = false;
                    }
                });
            } else {
                $this->processNonGlobalFilterField($filter);
            }
        }

        return $hasFilters;
    }

    private function processFixedFilters(): void
    {
        foreach ($this->payload->fixedFilters as $filter) {
            if ($filter->isEmpty()) {
                continue;
            }
            $this->processNonGlobalFilterField($filter);
        }

    }

    private function processGroupedFilters(Builder $builder, GroupedFilter $groupedFilter, string $operator): void
    {
        if ($groupedFilter->isGroup) {
            $builder->where(function (Builder $query) use ($groupedFilter) {
                foreach ($groupedFilter->constraints as $constraint) {
                    $this->processGroupedFilters($query, $constraint, $groupedFilter->operator);
                }
            });
        } else {
            $this->addFilterToField($groupedFilter->column, $groupedFilter->value, $groupedFilter->matchMode, $builder, $operator === 'or' ? 'orWhere' : 'where');
        }
    }

    private function processEachGroupedFilter($filter) {}

    private function processSorts(): void
    {
        $this->payload->sorts->each(function (Sort $sort) {
            $this->checkNestedColumnName($sort->field);
            $columnName = $sort->field;
            /** @var Field $field */
            $field = $this->payload->fields->where('name', $columnName)->first();
            if ($field?->source === FieldType::custom) {
                //ray(true);
            } else {
                $this->modifyColumnName($columnName, 'sortField');
            }
            $this->query->orderBy($columnName, $sort->direction);
        });
    }

    /**
     * @param  Collection<Field>  $fields
     */
    private function checkColumnsForRelations(Collection $fields): void
    {
        foreach ($fields as $field) {
            switch ($field->source) {
                case FieldType::relationCount:
                    $this->loadRelationCount(Str::beforeLast($field->name, '_count'));
                    break;
                case FieldType::relation:
                    $this->loadRelation(Str::beforeLast($field->name, '.'));
                    break;
                case FieldType::main:
                    //throw new Exception('To be implemented');
                case FieldType::json:
                case FieldType::mainCount:
                    break;
                    //throw new Exception('To be implemented');
                case FieldType::custom:
                    //throw new \Exception('To be implemented');
            }
        }
    }

    /**
     * @param  Data::class  $dataClassName
     * @return $this
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function setDataClass(string $dataClassName): self
    {
        $this->_dataClass = $dataClassName;

        return $this;
    }

    /**
     * @param  Closure<Collection>  $callback
     * @return $this
     */
    public function modifyItemsCollection(Closure $callback): self
    {
        $this->_itemsModifier = $callback;

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function proceed(): ResponseData
    {
        //        ray()->clearScreen();
        //        ray()->showQueries();

        if (! $this->payload) {
            $this->setPayload();
        }
        $payload = $this->payload;
        $this->processFixedFilters();
        if ($this->payload->groupedFilter) {
            $this->processGroupedFilters($this->query, $this->payload->groupedFilter, 'and');
        }
        $countQuery = $this->query->clone();
        $hasFilters = $this->processFilters();
        $this->checkColumnsForRelations($payload->fields);
        $this->processSorts();
        $this->processJoinsAndRelations($countQuery);
        $totalWithoutFilters = $countQuery->count();

        $this->processJoinsAndRelations($this->query);
        //Log::debug($hasFilters);
        $this->processAddedCustomColumns();

        $total = $hasFilters ? $this->query->count() : $totalWithoutFilters;

        //$columns = $this->setColumns();

        if ($payload->perPage !== -1) {
            $this->query
                ->offset($payload->first)
                ->limit($payload->perPage);
        }

        if (! empty($this->payload->includes)) {
            $this->query->with($this->payload->includes);
        }

        $items = $this->query->get();
        if ($this->_dataClass) {
            $items = $this->_dataClass::collect($items);
        }

        if ($this->_itemsModifier) {
            $items = call_user_func($this->_itemsModifier, $items);
        }

        return ResponseData::from([
            'data' => $items,
            'current_page' => $payload->page,
            'from' => $from = $payload->first + 1,
            'to' => $from + $payload->perPage,
            'per_page' => $payload->perPage,
            'last_page' => ceil($total / $payload->perPage),
            'total' => $total,
            'total_without_filters' => $totalWithoutFilters,
        ]);

    }

    private function processNonGlobalFilterField(Filter $filter): void
    {
        if ($filter->isMultiple()) {
            $this->query->where(function (Builder $query) use ($filter) {
                foreach ($filter->constraints as $constraint) {
                    $this->addFilterToField($filter->name, $constraint->value, $constraint->matchMode, query: $query, method: $filter->operator === 'or' ? 'orWhere' : 'where');
                }
            });
        } else {
            $this->addFilterToField($filter->name, $filter->value, $filter->matchMode);
        }
    }
}
