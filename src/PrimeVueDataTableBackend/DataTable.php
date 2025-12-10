<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use Carbon\Carbon;
use DB;
use Exception;
use HassanDomeDenea\HddLaravelHelpers\BaseModel;
use HassanDomeDenea\HddLaravelHelpers\Helpers\StringHelpers;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FieldType;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums\FilterMatchMode;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Payload;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\DateBetween;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\DateCompare;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\In;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\IsTruthy;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\Like;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\NotIn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kirschbaum\PowerJoins\JoinsHelper;
use Kirschbaum\PowerJoins\PowerJoinClause;
use Kirschbaum\PowerJoins\StaticCache;
use Spatie\LaravelData\Data;

use Throwable;
use Tpetry\QueryExpressions\Function\Conditional\Coalesce;
use Tpetry\QueryExpressions\Operator\Comparison\Between;
use Tpetry\QueryExpressions\Operator\Comparison\Equal;
use Tpetry\QueryExpressions\Operator\Comparison\GreaterThan;
use Tpetry\QueryExpressions\Operator\Comparison\GreaterThanOrEqual;
use Tpetry\QueryExpressions\Operator\Comparison\IsNull;
use Tpetry\QueryExpressions\Operator\Comparison\LessThan;
use Tpetry\QueryExpressions\Operator\Comparison\LessThanOrEqual;
use Tpetry\QueryExpressions\Operator\Comparison\NotEqual;
use Tpetry\QueryExpressions\Operator\Comparison\NotIsNull;
use Tpetry\QueryExpressions\Operator\Logical\CondNot;
use Tpetry\QueryExpressions\Value\Number;
use Tpetry\QueryExpressions\Value\Value;
use function request;

/**
 * @template TModel of BaseModel|Model
 * */
class DataTable
{
    private ?Payload $payload = null;

    /**
     * @var Builder<TModel>|\Spatie\QueryBuilder\QueryBuilder<TModel>|Relation|null
     */
    private Builder|Relation|null|\Spatie\QueryBuilder\QueryBuilder $query = null;

    private Builder|Relation|null|\Spatie\QueryBuilder\QueryBuilder $countQuery = null;

    protected array $joins = [];
    protected array $joinsMorphableTo = [];

    protected array $_addedSelects = [];

    protected array $relations = [];

    protected array $relationsCounts = [];
    protected Collection $relationsAggregates;

    protected ?string $mainTableName = null;

    /**
     * @var (callable(Collection):Collection) | null
     */
    protected $_itemsModifier = null;

    /**
     * @var class-string<Data> | null
     */
    protected mixed $_dataClass = null;

    /**
     * @param class-string<TModel>|Builder<TModel> $modelName
     * @return $this
     */
    public static function using(string|Builder $modelName): self
    {
        $newInstance = new self();
        $newInstance
            ->setModel($modelName)
            ->setPayload();
        return $newInstance;
    }

    public function __construct()
    {
        $this->relationsAggregates = collect();
    }

    public function setPayload($data = null): self
    {
        $this->payload = new Payload($data ?: request()->fluent());

        return $this;
    }

    public function getPayload(): Payload
    {
        return $this->payload;
    }

    /**
     * @param class-string<TModel>|Builder<TModel>|\Spatie\QueryBuilder\QueryBuilder<TModel> $modelName
     * @param class-string<Data> $dataClassName
     * @return $this
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function setModel(string|Builder|\Spatie\QueryBuilder\QueryBuilder|Relation $modelName, ?string $dataClassName = null): self
    {
        if (is_string($modelName)) {
            /** @var Builder<TModel> $query */
            $query = $modelName::query();

            $this->query = $query;
            $this->mainTableName = (new $modelName)->getModel()->getTable();
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

    public function joinRelation(string|array $relationName, ?string $morphableTo = null): self
    {
        if (is_array($relationName)) {
            foreach ($relationName as $name) {
                if (Arr::has($this->joins, $name)) {
                    continue;
                }
                $this->joins[] = $name;
                if ($morphableTo) {
                    $this->joinsMorphableTo[$name] = Relation::getMorphedModel($morphableTo) ?: Relation::getMorphAlias($morphableTo);
                }
            }
        } else {
            if (!Arr::has($this->joins, $relationName)) {
                $this->joins[] = $relationName;
                if ($morphableTo) {
                    $this->joinsMorphableTo[$relationName] = Relation::getMorphedModel($morphableTo) ?: Relation::getMorphAlias($morphableTo);
                }
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
            if (!Arr::has($this->relations, $relationName)) {
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
                $this->relationsCounts[] = Str::camel($name);
            }
        } else {
            if (!Arr::has($this->relationsCounts, $relationName)) {
                $this->relationsCounts[] = Str::camel($relationName);
            }

        }

        return $this;
    }

    /**
     * @param Builder<TModel> $query
     */
    private function processJoinsAndRelations(Builder|\Spatie\QueryBuilder\QueryBuilder|Relation $query): void
    {
        $customJoinCalls = [];

        foreach (array_unique($this->joins) as $join) {
            $joinName = $join;
            $currentParent = $this->getQuery()->newModelInstance();
            if (Str::contains($joinName, '.')) {
                $joinNamesList = explode('.', $joinName);
                $joinNamesAliases = [];
                $lastJoinNameAlias= null;
                foreach ($joinNamesList as $joinNameAlias) {
                    $camelJoinNameAlias = Str::camel($joinNameAlias);
                    if(method_exists($currentParent,$camelJoinNameAlias.'OnJoin')){
                        $customJoinCalls[$camelJoinNameAlias] = [clone $currentParent, $camelJoinNameAlias.'OnJoin', [$query,$joinNameAlias,...$lastJoinNameAlias ? [$lastJoinNameAlias] : []]];
                    }
                    $currentParent = $currentParent->{$camelJoinNameAlias}()->getRelated();
                    if(empty($customJoinCalls)){
                        $joinNamesAliases[$camelJoinNameAlias] = fn($join2) => $join2->as($joinNameAlias);
                    }
                    $lastJoinNameAlias = $joinNameAlias;
                }
                if(filled($joinNamesAliases)){
                    if(count($joinNamesAliases) > 1){
                        $query->leftJoinRelationship(join(".", array_keys($joinNamesAliases)), $joinNamesAliases, morphable: $this->joinsMorphableTo[$join] ?? null);
                    }else{
                        $joinName = array_key_first($joinNamesAliases);
                        $query->leftJoinRelationship($joinName, $joinNamesAliases[$joinName], morphable: $this->joinsMorphableTo[$join] ?? null);
                    }
                }
            } else {
                $camelJoinNameAlias=Str::camel($joinName);
                if(method_exists($currentParent,$camelJoinNameAlias.'OnJoin')){
                    $customJoinCalls[$camelJoinNameAlias] = [clone $currentParent, $camelJoinNameAlias.'OnJoin', [$query,$joinName]];
                }else{
                    $query->leftJoinRelationship($camelJoinNameAlias, fn(PowerJoinClause $join) => $join->as($joinName), morphable: $this->joinsMorphableTo[$join] ?? null);
                }
            }
        }

        if(filled($customJoinCalls)){
            $query->select("$this->mainTableName.*");
            foreach ($customJoinCalls as $customJoinCall) {
                $customJoinCall[0]->{$customJoinCall[1]}(...$customJoinCall[2]);
            }
        }

        if (!empty($this->relations)) {
            $query->with(array_unique(array_map(fn($i)=>Str::camel($i),$this->relations)));
        }

        if (!empty($this->relationsCounts)) {
            $query->withCount(array_unique(array_map(fn($i)=>Str::camel($i),$this->relations)));
        }

        if ($this->relationsAggregates->isNotEmpty()) {
            $this->relationsAggregates->each(function ($columnDefinition, $relation) use ($query) {
                $query->joinRelationAggregate(Str::camel($relation), $columnDefinition->toArray());
            });
        }
    }

    private function processAddedCustomColumns(): void
    {

        foreach ($this->_addedSelects as $addedSelect) {
            $this->query->addSelect($addedSelect);
        }
    }

    /**
     * @param callable(Builder<TModel>):void $callback
     * @return $this
     */
    public function modifyQuery(callable $callback): self
    {
        $callback($this->query);

        return $this;
    }

    /**
     * @return Builder<TModel>|\Spatie\QueryBuilder\QueryBuilder|Relation
     */
    public function getQuery(): Builder|Relation|\Spatie\QueryBuilder\QueryBuilder
    {
        return $this->query->clone();
    }

    /**
     * @return Builder<TModel>|\Spatie\QueryBuilder\QueryBuilder|Relation
     */
    public function getCountQuery(): Builder|Relation|\Spatie\QueryBuilder\QueryBuilder
    {
        return $this->countQuery->clone();
    }

    public function checkNestedColumnName(string $column, Field|null $field = null): void
    {
        if (Str::contains($column, '.')) {
            $field ??= $this->payload->fields->where('filterField', $column)->first();
            if (!$field?->sortSource->in([FieldType::json, FieldType::relationMany])) {
                $this->joinRelation(Str::beforeLast($column, '.'), $field?->morphableTo);
            }
        }
    }

    public function modifyColumnName(&$columnName, $sortOrFilterColumnName = 'name'): string
    {
        /** @var Field|null $field */
        $field = $this->payload->fields->firstWhere($sortOrFilterColumnName, $columnName);

        $sourceField = match ($sortOrFilterColumnName) {
            'sortField' => 'sortSource',
            'filterField' => 'filterSource',
            default => 'source',
        };
        if ($field?->{$sourceField}->in([FieldType::relationAggregate])) {
            return $columnName;
        }


        if (!Str::contains($columnName, '.')) {
            if ($sortOrFilterColumnName === 'sortField' && $field?->sortSource->value === FieldType::relationCount->value) {
                // Empty
            } else {
                if (!$field?->source->in([FieldType::relationCount, FieldType::jsonArray, FieldType::relationMany])) {
                    $columnName = $this->mainTableName . '.' . $columnName;
                }
            }


        } elseif ($field?->source->is(FieldType::json)) {
            $columnName = Str::replace('.', '->', $field->filterField);
        } elseif (substr_count($columnName, '.') > 1) {
            $relationName = Str::afterLast(Str::beforeLast($columnName, '.'), '.');
            $columnName =
                $relationName . "." . Str::after($columnName, $relationName . ".");
        }
        return $columnName;
    }

    private function addFilterToField(string $columnName, mixed $value = null, FilterMatchMode $matchMode = FilterMatchMode::contains, Builder|Relation|\Spatie\QueryBuilder\QueryBuilder|null $query = null, string $boolean = 'and', $skipCheckingRelations = false): void
    {
        $isRelationCountField = false;
        $relationManyField = false;

        /** @var Field|null $field */
        $field = $this->payload->fields->firstWhere('filterField', $columnName);
        /** @var Builder $targetQuery */
        $targetQuery = $query ?: $this->query;


        if (!$skipCheckingRelations) {
            $this->checkNestedColumnName($columnName);
            $this->modifyColumnName($columnName, 'filterField');
            if ($field?->filterSource->in([FieldType::relationCount])) {
                $columnName = new Coalesce([$columnName, new Number(0)]);
                //$isRelationCountField = true;
                // [$columnName] = $targetQuery->relationAggregateQuery(Str::beforeLast($columnName, '_count'), '*', 'count', false);
            } elseif ($field?->filterSource->in([FieldType::relationMany])) {
                $relationManyField = Str::beforeLast($columnName, '.');
                $columnName = Str::afterLast($columnName, '.');
            }
        }




        if ($relationManyField) {

            $filterCallback = function (Builder $q) use ($relationManyField, $matchMode, $value, $columnName) {
                $this->addFilterToField($relationManyField . '.' . $columnName, $value, $matchMode, $q, skipCheckingRelations: true);
            };
            if ($boolean === 'and') {
                $targetQuery->whereHas($relationManyField, $filterCallback);
            } else {
                $targetQuery->orWhereHas($relationManyField, $filterCallback);
            }
            return;
        }


        $columnExpression = null;
        $operatorExpression = null;
        $valueExpression = null;

        if (is_null($value) && $matchMode->notIn([FilterMatchMode::isNull, FilterMatchMode::isNotNull])) {
            return;
        }

        switch ($matchMode) {
            case FilterMatchMode::equals:
                if ($value === 'true' || $value === 'false') {
                    $columnExpression = new IsTruthy($columnName, $value === 'true');
                } else {

                    $columnExpression = new Equal($columnName, is_numeric($value) ? new Number($value) : new Value($value));
                }
                break;
            case FilterMatchMode::notEquals:
                $columnExpression = new NotEqual($columnName, new Value($value));
                break;
            case FilterMatchMode::containsAll:
                $value = explode(' ', preg_replace('!\s+!', ' ', $value));
                $columnExpression = function (Builder $q) use (
                    $columnName,
                    $value,
                    $isRelationCountField,
                ) {
                    foreach ($value as $v) {
                        if (!$isRelationCountField) {
                            $expression = new Like($columnName, new Value($v));
                        } else {
                            $expression = new Equal($columnName, new Value($v));
                        }
                        $q->where($expression);
                    }
                };
                break;
            case FilterMatchMode::containsAny:
                $value = explode(' ', preg_replace('!\s+!', ' ', $value));
                $columnExpression = function (Builder $q) use (
                    $columnName,
                    $value,
                    $isRelationCountField,
                ) {
                    foreach ($value as $v) {
                        if (!$isRelationCountField) {
                            $expression = new Like($columnName, new Value($v));
                        } else {
                            $expression = new Equal($columnName, new Value($v));
                        }
                        $q->where($expression, boolean: 'or');
                    }
                };
                break;
            case FilterMatchMode::contains:
                $columnExpression = new Like($columnName, new Value($value));
                break;
            case FilterMatchMode::isNull:
                $columnExpression = new IsNull($columnName);
                break;
            case FilterMatchMode::isNotNull:
                $columnExpression = new NotIsNull($columnName);
                break;
            case FilterMatchMode::between:
                $value1 = is_array($value) ? $value[0] : $value;
                $value2 = is_array($value) ? $value[1] : $value;
                $columnExpression = new Between($columnName, new Number($value1), new Number($value2));
                break;
            case FilterMatchMode::notBetween:
                $value1 = is_array($value) ? $value[0] : $value;
                $value2 = is_array($value) ? $value[1] : $value;
                $columnExpression = new CondNot(new Between($columnName, new Number($value1), new Number($value2)));
                break;
            case FilterMatchMode::whereIn:
                if ($field->source->is(FieldType::jsonArray)) {
                    $columnExpression = function (Builder $q) use ($columnName, $value) {
                        foreach ($value as $v) {
                            $q->whereJsonContains($columnName, $v);
                        }
                    };
                } else {
                    $columnExpression = new In($columnName, $value);
                }
                break;
            case FilterMatchMode::whereNotIn:
                if ($field->source->is(FieldType::jsonArray)) {
                    $columnExpression = function (Builder $q) use ($columnName, $value) {
                        foreach ($value as $v) {
                            $q->whereJsonDoesntContain($columnName, $v);
                        }
                    };
                } else {
                    $columnExpression = new In($columnName, $value, not: true);
                }
                break;
            case FilterMatchMode::notContains:
                $columnExpression = new Like($columnName, new Value($value), not: true);
                break;
            case FilterMatchMode::startsWith:
                $columnExpression = new Like($columnName, new Value($value), matchAnyStart: false);
                break;
            case FilterMatchMode::endsWith:
                $columnExpression = new Like($columnName, new Value($value), matchAnyEnd: false);
                break;
            case FilterMatchMode::dateIs:
                $columnExpression = new DateCompare($columnName, '=', $value);
                break;
            case FilterMatchMode::dateIsNot:
                $columnExpression = new DateCompare($columnName, '!=', $value);
                break;
            case FilterMatchMode::dateBefore:
                $columnExpression = new DateCompare($columnName, '<', $value);
                break;
            case FilterMatchMode::dateAfter:
                $columnExpression = new DateCompare($columnName, '>', $value);
                break;
            case FilterMatchMode::dateIsOrBefore:
            case FilterMatchMode::dateLte:
                $columnExpression = new DateCompare($columnName, '<=', $value);
                break;
            case FilterMatchMode::dateIsOrAfter:
            case FilterMatchMode::dateGte:
                $columnExpression = new DateCompare($columnName, '>=', $value);
                break;
            case FilterMatchMode::dateBetween:
                $value1 = is_array($value) ? Carbon::parse($value[0]) : $value;
                $value2 = is_array($value) ? Carbon::parse($value[1]) : $value;
                $columnExpression = new DateBetween($columnName, $value1, $value2);
                break;
            case FilterMatchMode::dateNotBetween:
                $value1 = is_array($value) ? Carbon::parse($value[0]) : $value;
                $value2 = is_array($value) ? Carbon::parse($value[1]) : $value;
                $columnExpression = new CondNot(new DateBetween($columnName, $value1, $value2));
                break;
            case FilterMatchMode::lessThan:
                $columnExpression = new LessThan($columnName, new Number($value));
                break;
            case FilterMatchMode::lessThanOrEquals:
                $columnExpression = new LessThanOrEqual($columnName, new Number($value));
                break;
            case FilterMatchMode::greaterThan:
                $columnExpression = new GreaterThan($columnName, new Number($value));
                break;
            case FilterMatchMode::greaterThanOrEquals:
                $columnExpression = new GreaterThanOrEqual($columnName, new Number($value));
                break;
        }
        if ($columnExpression !== null) {
            $targetQuery->where($columnExpression, $operatorExpression, $valueExpression, boolean: $boolean);
        }

    }

    /**
     * @param Builder<TModel>|null $query
     * @param 'where'|'whereNot' $method
     * @deprecated
     */
    private function addFilterToFieldOld(string $columnName, mixed $value, FilterMatchMode $matchMode = FilterMatchMode::contains, ?Builder $query = null, string $method = 'where', bool $skipChecking = false): void
    {
        $nameOfValueParameter = 'value';
        $isRelationCountField = false;
        $isRelationManyField = false;
        $originalMethod = $method;
        if (!$skipChecking) {
            $this->checkNestedColumnName($columnName);
            $this->modifyColumnName($columnName, 'filterField');
            /** @var Field|null $field */
            $field = $this->payload->fields->firstWhere('filterField', $columnName);
            if ($field?->filterSource->in([FieldType::relationCount])) {
                $method .= 'Has';
                $columnName = Str::beforeLast($columnName, '_count');
                $nameOfValueParameter = 'count';
                $isRelationCountField = true;
            } elseif ($field?->filterSource->in([FieldType::relationMany])) {
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
                $this->addFilterToFieldOld($isRelationManyField . '.' . $columnName, $value, $matchMode, $q, 'where', true);
            });

            return;
        }


        switch ($matchMode) {
            case FilterMatchMode::contains:
                $operator = 'LIKE';
                $value = '%' . $value . '%';
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
                $value = '%' . $value . '%';
                break;
            case FilterMatchMode::startsWith:
                $operator = 'LIKE';
                $value = $value . '%';
                break;
            case FilterMatchMode::endsWith:
                $operator = 'LIKE';
                $value = '%' . $value;
                break;
            case FilterMatchMode::equals:
                $operator = '=';
                if ($value === 'true' || $value === 'false') {
                    $value = $value === 'true';
                }
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
            case FilterMatchMode::dateLte:
            case FilterMatchMode::dateIsOrBefore:
                $operator = '<=';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateGte:
            case FilterMatchMode::dateIsOrAfter:
                $operator = '>=';
                $method .= 'Date';
                break;
            case FilterMatchMode::dateIsNot:
                $operator = '!=';
                $method .= 'Date';
                break;
            case FilterMatchMode::between:
            case FilterMatchMode::dateBetween:
                $method .= 'Between';
                $nameOfValueParameter = 'values';
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
                $value = +$value;
                break;
            case FilterMatchMode::lessThanOrEquals:
                $operator = '<=';
                $value = +$value;
                break;
            case FilterMatchMode::greaterThan:
                $operator = '>';
                $value = +$value;
                break;
            case FilterMatchMode::greaterThanOrEquals:
                $operator = '>=';
                $value = +$value;
                break;
            case FilterMatchMode::containsAny:
                // TODO: Implement
                break;
            // throw new \Exception('To be implemented');
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
                        if (!$isRelationCountField) {
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
                if (!$onlyColumnName) {
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

                        $this->addFilterToField($columnName, $filter->value, $filter->matchMode, query: $query, boolean: $firstWhereStatement ? 'and' : 'or');
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

    private function processGroupedFilters(Builder|\Spatie\QueryBuilder\QueryBuilder|Relation $builder, ?GroupedFilter $groupedFilter, $operator = null): bool
    {

        $isEmpty = true;
        if(filled($groupedFilter)) {
            if ($groupedFilter->isGroup) {
                $builder->where(function (Builder $query) use (&$isEmpty, $groupedFilter) {
                    foreach ($groupedFilter->fields as $i) {
                        $loopIsNotEmpty = $this->processGroupedFilters($query, $i, $groupedFilter->operator);
                        if ($loopIsNotEmpty) {
                            $isEmpty = false;
                        }
                    }
                }, boolean: $operator ?: $groupedFilter->operator);
            } else {
                if (!is_null($groupedFilter->value) || $groupedFilter->matchMode->in([FilterMatchMode::isNull, FilterMatchMode::isNotNull])) {
                    $this->addFilterToField($groupedFilter->field, $groupedFilter->value, $groupedFilter->matchMode, $builder, $operator ?: $groupedFilter->operator);
                    $isEmpty = false;
                }
            }
        }
        return !$isEmpty;
    }

    function checkSortableAndFilterableFieldsForJoining()
    {
        $this->payload->sorts->each(function (Sort $sort) {
            $field = $this->payload->fields->where('name', $sort->field)->first();
            $this->checkNestedColumnName($field?->sortField ?: $sort->field, $field);
        });
    }


    private function processSorts(): void
    {
        $this->payload->sorts->each(function (Sort $sort) {
            /** @var Field|null $field */
            $field = $this->payload->fields->firstWhere('name', $sort->field);

            $columnName = $field?->sortField ?: $sort->field;

            if ($field?->source->in([FieldType::custom])) {
                // TODO: Adding Custom Handling
            } else {
                $this->modifyColumnName($columnName, 'sortField');
            }
            $this->query->orderBy($columnName, $sort->direction);
        });
    }

    private function checkColumnsForRelations(): void
    {
        $fields = $this->payload->fields;
        foreach ($fields as $field) {
            switch ($field->source) {
                case FieldType::relationCount:
//                    $this->loadRelationCount(Str::beforeLast($field->name, '_count'));
                    $this->joinRelationAggregate($field->name, $field->source);
                    break;
                case FieldType::relationAggregate:
                    $this->joinRelationAggregate($field->name, $field->source);
                    break;
                case FieldType::relation:
                    $this->loadRelation(Str::beforeLast($field->name, '.'));
                    break;
                case FieldType::main:
                case FieldType::json:
                case FieldType::custom:
                case FieldType::mainCount:
                case FieldType::jsonArray:
                case FieldType::relationMany:
                    break;
            }
        }
    }

    /**
     * @param class-string<Data>|null $dataClassName
     * @return $this
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function setDataClass(string|null $dataClassName = null): self
    {
        $this->_dataClass = $dataClassName;

        return $this;
    }

    /**
     * @param callable(Collection):Collection $callback
     * @return $this
     */
    public function modifyItemsCollection(callable $callback): self
    {
        $this->_itemsModifier = $callback;

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function proceed(): ResponseData
    {
        if (!$this->payload) {
            $this->setPayload();
        }
        $payload = $this->payload;
        $this->processFixedFilters();
        $this->processGroupedFilters($this->query, $this->payload->fixedGroupedFilters, 'and');
        $this->checkSortableAndFilterableFieldsForJoining();
        $this->checkColumnsForRelations();
        $countQuery = $this->query->clone();

        $hasFilters = $this->processFilters();
        if ($this->payload->groupedFilter) {
            $hasGroupedFilters = $this->processGroupedFilters($this->query, $this->payload->groupedFilter, 'and');

            if (!$hasFilters && $hasGroupedFilters) {
                $hasFilters = true;
            }
        }
        $this->processJoinsAndRelations($countQuery);

        $countColumn = "*";
        if (!empty($this->joins)) {
            // $countColumn = DB::raw($this->mainTableName . '.*');
        }
        $totalWithoutFilters = $countQuery->count($countColumn);
        $this->countQuery = $countQuery;
        $joinHelper = JoinsHelper::make($this->query->getModel());
        $joinHelper->clear();
        $this->processJoinsAndRelations($this->query);
        $this->processSorts();
        $this->processAddedCustomColumns();

        $total = $hasFilters ? $this->query->count($countColumn) : $totalWithoutFilters;

        // $columns = $this->setColumns();

        if ($payload->perPage !== -1) {
            $this->query
                ->offset($payload->first)
                ->limit($payload->perPage);
        }

        if (!empty($this->payload->includes)) {
            $this->query->with($this->payload->includes);
        }

        $items = $this->query->get();
        if ($this->_dataClass) {
            $items = $this->_dataClass::collect($items);
            if($payload->options->onlyRequestedColumns){
                $fields = $payload->fields->map(fn(Field $field) => Str::camel($field->name))->toArray();
                if(filled($payload->options->primaryKey)){
                    $fields[] = $payload->options->primaryKey;
                }
                $items->map(fn($item) => $item->only(...$fields));
                // $items->only('name');
//                $items->only($payload->fields->map(fn(Field $field) => $field->name));
            }
        }

        if ($this->_itemsModifier) {
            $items = call_user_func($this->_itemsModifier, $items);
        }

        return ResponseData::from([
            'data' => $items,
            'current_page' => $payload->page,
            'from' => $from = min($payload->first + 1, $total),
            'to' => match ($payload->perPage) {
                -1 => $total,
                default => $total === 0 ? 0 : $from - 1 + min($payload->perPage, $items->count())
            },
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
                    $this->addFilterToField($filter->name, $constraint->value, $constraint->matchMode, query: $query, boolean: $filter->operator);
                }
            });
        } else {
            $this->addFilterToField($filter->name, $filter->value, $filter->matchMode);
        }
    }

    private function isMorphToRelationship(string $relationName): bool
    {
        $model = $this->query->getModel();
        if (!method_exists($model, $relationName)) {
            return false;
        }

        try {
            $relation = $model->{$relationName}();
            return $relation instanceof MorphTo;
        } catch (Throwable) {
            return false;
        }
    }

    private function joinRelationAggregate(string $fullFieldName, ?FieldType $source = null)
    {
        if (Str::contains($fullFieldName, '.')) {
            $relation = Str::beforeLast($fullFieldName, '.');
            $column = str($fullFieldName)->after($relation . '.')->beforeLast('_')->toString();
            $aggregateFunction = str($fullFieldName)->afterLast('_')->toString();
        } else {
            if ($source?->is(FieldType::relationCount)) {
                $relation = Str::beforeLast($fullFieldName, '_count');
                $aggregateFunction = 'count';
                $column = '*';
            } else {
                [$relation, $aggregateFunction, $column] = StringHelpers::parseAggregateString($fullFieldName);
            }
        }
        if (!$this->relationsAggregates->has($relation)) {
            $this->relationsAggregates[$relation] = collect();
        }
        if ($this->relationsAggregates[$relation]->where('column', $column)->where('function', $aggregateFunction)->isEmpty()) {
            $this->relationsAggregates[$relation][] = [
                'column' => $column,
                'function' => $aggregateFunction,
            ];
        }

    }


}
