<?php

namespace HassanDomeDenea\HddLaravelHelpers\Providers;

use DB;
use Exception;
use HassanDomeDenea\HddLaravelHelpers\BaseModel;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\In;
use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\NotIn;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Kirschbaum\PowerJoins\FakeJoinCallback;
use Kirschbaum\PowerJoins\PowerJoinClause;
use Spatie\LaravelData\Attributes\Validation\Between;
use Str;
use Tpetry\QueryExpressions\Function\Aggregate\AggregateExpression;
use Tpetry\QueryExpressions\Function\Aggregate\Avg;
use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Function\Aggregate\Max;
use Tpetry\QueryExpressions\Function\Aggregate\Min;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Function\Comparison\StrListContains;
use Tpetry\QueryExpressions\Function\Conditional\Coalesce;
use Tpetry\QueryExpressions\Language\Alias;
use Tpetry\QueryExpressions\Operator\Comparison\IsNull;
use Tpetry\QueryExpressions\Value\Number;


class CustomRelationProvider extends ServiceProvider
{
    /**
     * @throws Exception
     */
    public function register(): void
    {
        $this->addHavingMacros();
        $this->addWithWhereAggregates();

        Builder::macro('isSqlite', function () {
            return $this->getConnection() instanceof SQLiteConnection;
        });
//        $aggregateMethods = ['Avg', 'Max', 'Min', 'Sum'];
    }

    private function addHavingMacros(): void
    {

        Builder::macro('havingIn', function (string $columnName, array $values = [], string $boolean = 'and', bool $not = false) {
            $values = array_values($values);
            $valuesAsQuestionMarks = implode(', ', Arr::map($values, fn() => "?"));
            $operator = $not ? 'not in' : 'in';
//            $this->having(new Between())
            return $this->havingRaw("`$columnName` $operator ( $valuesAsQuestionMarks )", [$values]);
        });
        Builder::macro('havingNotIn', function ($columnName, $values = [], string $boolean = 'and') {
            return $this->havingIn($columnName, $values, 'or', $boolean, true);
        });
        Builder::macro('orHavingIn', function ($columnName, $values = [], $not = false) {
            return $this->havingIn($columnName, $values, 'or', $not);
        });
        Builder::macro('orHavingNotIn', function ($columnName, $values = []) {
            return $this->orHavingIn($columnName, $values, true);
        });
    }

    /**
     * @param 'Avg'| 'Count'| 'Max'| 'Min'| 'Sum' $aggregateMethod
     * @return class-string<AggregateExpression>
     * @throws Exception
     */
    private function getAggregateMethodExpression(string $aggregateMethod): string
    {
        return match ($aggregateMethod) {
            'Avg' => Avg::class,
            'Count' => Count::class,
            'Max' => Max::class,
            'Min' => Min::class,
            'Sum' => Sum::class,
            default => throw new Exception("Aggregate method $aggregateMethod is not supported")
        };
    }

    private function addWithWhereAggregates(): void
    {
        Builder::macro('relationAggregateQuery', function ($relation, $column, $function, $load = true) {
            if (empty($relation)) {
                return $this;
            }

            if (is_null($this->query->columns)) {
                $this->query->select([$this->query->from . '.*']);
            }

            $relations = is_array($relation) ? $relation : [$relation];

            foreach ($this->parseWithRelations($relations) as $name => $constraints) {
                // First we will determine if the name has been aliased using an "as" clause on the name
                // and if it has we will extract the actual relationship name and the desired name of
                // the resulting column. This allows multiple aggregates on the same relationships.
                $segments = explode(' ', $name);

                unset($alias);

                if (count($segments) === 3 && \Illuminate\Support\Str::lower($segments[1]) === 'as') {
                    [$name, $alias] = [$segments[0], $segments[2]];
                }

                $alias ??= Str::snake(
                    preg_replace('/[^[:alnum:][:space:]_]/u', '', "$name $function {$this->getQuery()->getGrammar()->getValue($column)}")
                );

                $relation = $this->getRelationWithoutConstraints($name);
                if ($function) {
                    if ($this->getQuery()->getGrammar()->isExpression($column)) {
                        $aggregateColumn = $this->getQuery()->getGrammar()->getValue($column);
                    } else {
                        $hashedColumn = $this->getRelationHashedColumn($column, $relation);

                        $aggregateColumn = $this->getQuery()->getGrammar()->wrap(
                            $column === '*' ? $column : $relation->getRelated()->qualifyColumn($hashedColumn)
                        );
                    }

                    $expression = $function === 'exists' ? $aggregateColumn : sprintf('%s(%s) as %s', $function, $aggregateColumn, $alias);
                } else {
                    $expression = $this->getQuery()->getGrammar()->getValue($column);
                }


                // Here, we will grab the relationship sub-query and prepare to add it to the main query
                // as a sub-select. First, we'll get the "has" query and use that to get the relation
                // sub-query. We'll format this relationship name and append this column if needed.
                $query = $relation->getRelationExistenceQuery(
                    $relation->getRelated()->newQuery(), $this, new Expression($expression)
                )->setBindings([], 'select');

                $query->callScope($constraints);

                $query = $query->mergeConstraintsFrom($relation->getQuery())->toBase();

                // If the query contains certain elements like orderings / more than one column selected
                // then we will remove those elements from the query so that it will execute properly
                // when given to the database. Otherwise, we may receive SQL errors or poor syntax.
                $query->orders = null;
                $query->setBindings([], 'order');

                if (count($query->columns) > 1) {
                    $query->columns = [$query->columns[0]];
                    $query->bindings['select'] = [];
                }


                if ($load) {
                    $this->selectSub(
                        $query,
                        $alias
                    );
                }
                return [$query, $alias];
            }
        });


        Builder::macro('joinRelationAggregate', function ($relation, $columns) {
            /** @var \Illuminate\Database\Query\Builder $query */
            [$query, $alias] = $this->relationAggregateQuery($relation, $columns[0]['column'] ?? throw new Exception("Columns must not be empty"), $columns[0]['function'], false);
            $query->wheres = array_filter($query->wheres, function ($where) use (&$columnWheres) {
                if ($where['type'] === 'Column') {
                    $columnWheres[] = $where;
                    return false;
                } else {
                    return true;
                }
            });
            /** @var Relation $relationDefinition */
            $relationDefinition = $this->getModel()->{$relation}();
            $tableName = $relationDefinition->getModel()->getTable();

            $query->select([]);
            foreach ($columns as $column){
                $columnAlias = Str::snake($relation . ' ' . $column['function']. '  ' . $column['column'] );
                $aggregateMethod = match (strtolower($column['function'])) {
                    'avg' => Avg::class,
                    'count' => Count::class,
                    'max' => Max::class,
                    'min' => Min::class,
                    'sum' => Sum::class,
                    default => throw new Exception("Aggregate method $column[function] is not supported")
                };
                $query->addSelect(new Alias(new $aggregateMethod($column['column']),$columnAlias));

                $this->addSelect(new Alias(new Coalesce(["$tableName.$columnAlias",new Number(0)]), $columnAlias));
            }
            foreach ($columnWheres as &$where) {
                if (Str::contains($where['first'], $tableName)) {
                    $query->groupBy($where['first']);
                    $query->addSelect($where['first']);
                }
                if (Str::contains($where['second'], $tableName)) {
                    $query->groupBy($where['second']);
                    $query->addSelect($where['second']);
                }
            }
            $this->leftJoinSub($query, $tableName, function (JoinClause $join) use ($tableName, $columnWheres) {
                foreach ($columnWheres as $where) {
                    $first = $where['first'];
                    $second = $where['second'];
                    $join->on($first, $where['operator'], $second, $where['boolean']);
                }
            });


            return $this;
        });

        // Where and Load

        // This is Main One:
        Builder::macro('withWhereAggregate', function ($relation, $column, $function = null, $operator = '=', $value = null, string $boolean = 'and', bool $not = false, $load = true) {
            $doJoin = config()->boolean('hdd-laravel-helpers.with-where-aggregate-use-joins', true);

            /** @var \Illuminate\Database\Query\Builder $query */
            [$query, $alias] = $this->relationAggregateQuery($relation, $column, $function, $load && !$doJoin);
            if ($load && $doJoin) {
                $columnWheres = [];
                $query->wheres = array_filter($query->wheres, function ($where) use (&$columnWheres) {
                    if ($where['type'] === 'Column') {
                        $columnWheres[] = $where;
                        return false;
                    } else {
                        return true;
                    }
                });
                /** @var Relation $relation */
                $relation = $this->getModel()->{$relation}();
                $tableName = $relation->getModel()->getTable();
                $aliasTable = $alias . '_joined_table';
                foreach ($columnWheres as &$where) {
                    if (Str::contains($where['first'], $tableName)) {
                        $query->groupBy($where['first']);
                        $query->addSelect($where['first'] . ' as ' . Str::after($where['first'], '.'));
                        $where['first'] = Str::replaceFirst($tableName, $aliasTable, $where['first']);
                    }
                    if (Str::contains($where['second'], $tableName)) {
                        $query->groupBy($where['second']);
                        $query->addSelect($where['second'] . ' as ' . Str::after($where['second'], '.'));
                        $where['second'] = Str::replaceFirst($tableName, $aliasTable, $where['second']);
                    }
                }
                $this->leftJoinSub($query, $aliasTable, function (JoinClause $join) use ($aliasTable, $tableName, $columnWheres) {
                    foreach ($columnWheres as $where) {
                        $first = $where['first'];
                        $second = $where['second'];
                        $join->on($first, $where['operator'], $second, $where['boolean']);;
                    }
                })->addSelect("$aliasTable.$alias");

                $query = "$aliasTable.$alias";
            }

            switch (strtolower($operator)) {
                case 'in':
                    $this->where(new In($query, $value, $not), null, null, $boolean);
                    break;
                case 'not_in':
                case 'not in':
                case 'notin':
                    $this->where(new NotIn($query, $value), null, null, $boolean);
                    break;
                case 'null':
                    $this->whereRaw("( " . ($query instanceof \Illuminate\Database\Query\Builder ? $query->toRawSql() : $query) . " is " . ($not ? " not " : "") . " null )", [], $boolean);
                    break;
                case 'not_null':
                case 'not null':
                case 'notnull':
                    $this->whereRaw("( " . ($query instanceof \Illuminate\Database\Query\Builder ? $query->toRawSql() : $query) . " is not null )", [], $boolean);
                    break;
                default:
                    if ($value === null) {
                        $operator = '=';
                        $value = $operator;
                    }
                    $whereMethod = $not ? 'whereNot' : 'where';
                    $this->{$whereMethod}($query, $operator, $value, $boolean);
            }

            return $this;
        });

        Builder::macro('orWithWhereAggregate', function ($relation, $column, $function = null, $operator = '=', $value = null, bool $not = false) {
            return $this->withWhereAggregate($relation, $column, $function, $operator, $value, 'or', $not);
        });
        Builder::macro('withWhereAggregateNot', function ($relation, $column, $function = null, $operator = '=', $value = null, string $boolean = 'and') {
            return $this->withWhereAggregate($relation, $column, $function, $operator, $value, $boolean, true);
        });
        Builder::macro('orWithWhereAggregateNot', function ($relation, $column, $function = null, $operator = '=', $value = null) {
            return $this->withWhereAggregate($relation, $column, $function, $operator, $value, 'or', true);
        });

        // Only Where
        Builder::macro('whereAggregate', function ($relation, $column, $function = null, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, $function, $operator, $value, $boolean, $not, false);
        });

        Builder::macro('orWhereAggregate', function ($relation, $column, $function = null, $operator = '=', $value = null, bool $not = false) {
            return $this->whereAggregate($relation, $column, $function, $operator, $value, 'or', $not);
        });
        Builder::macro('whereAggregateNot', function ($relation, $column, $function = null, $operator = '=', $value = null, string $boolean = 'and') {
            return $this->whereAggregate($relation, $column, $function, $operator, $value, $boolean, true);
        });
        Builder::macro('orWhereAggregateNot', function ($relation, $column, $function = null, $operator = '=', $value = null) {
            return $this->whereAggregate($relation, $column, $function, $operator, $value, 'or', true);
        });


        // In and Not In
        Builder::macro('withWhereAggregateIn', function ($relation, $column, $function = null, $value = [], string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, $function, 'in', $value, $boolean, $not);
        });

        Builder::macro('orWithWhereAggregateIn', function ($relation, $column, $function = null, $value = [], bool $not = false) {
            return $this->withWhereAggregate($relation, $column, $function, 'in', $value, 'or', $not);
        });

        Builder::macro('withWhereAggregateNotIn', function ($relation, $column, $function = null, $value = null, string $boolean = 'and') {
            return $this->withWhereAggregate($relation, $column, $function, 'notIn', $value, $boolean);
        });

        Builder::macro('orWithWhereAggregateNotIn', function ($relation, $column, $function = null, $value = null) {
            return $this->withWhereAggregate($relation, $column, $function, 'notIn', $value, 'or');
        });

        // In and Not In - Where Only
        Builder::macro('whereAggregateIn', function ($relation, $column, $function = null, $value = null, string $boolean = 'and', bool $not = false) {
            return $this->whereAggregate($relation, $column, $function, 'in', $value, $boolean, $not);
        });

        Builder::macro('orWhereAggregateIn', function ($relation, $column, $function = null, $value = null, bool $not = false) {
            return $this->whereAggregate($relation, $column, $function, 'in', $value, 'or', $not);
        });

        Builder::macro('whereAggregateNotIn', function ($relation, $column, $function = null, $value = null, string $boolean = 'and') {
            return $this->whereAggregate($relation, $column, $function, 'notIn', $value, $boolean, true);
        });

        Builder::macro('orWhereAggregateNotIn', function ($relation, $column, $function = null, $value = null) {
            return $this->whereAggregate($relation, $column, $function, 'notIn', $value, 'or');
        });

        // Null and Not Null - With Where
        Builder::macro('withWhereAggregateNull', function ($relation, $column, $function = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, $function, 'null', null, $boolean, $not);
        });

        Builder::macro('orWithWhereAggregateNull', function ($relation, $column, $function = null, bool $not = false) {
            return $this->withWhereAggregate($relation, $column, $function, 'null', null, 'or', $not);
        });

        Builder::macro('withWhereAggregateNotNull', function ($relation, $column, $function = null, string $boolean = 'and') {
            return $this->withWhereAggregate($relation, $column, $function, 'notNull', null, $boolean);
        });

        Builder::macro('orWithWhereAggregateNotNull', function ($relation, $column, $function = null) {
            return $this->withWhereAggregate($relation, $column, $function, 'notNull', null, 'or');
        });

        // Null and Not Null - Where Only
        Builder::macro('whereAggregateNull', function ($relation, $column, $function = null, string $boolean = 'and', bool $not = false) {
            return $this->whereAggregate($relation, $column, $function, 'null', null, $boolean, $not);
        });
        Builder::macro('orWhereAggregateNull', function ($relation, $column, $function = null, bool $not = false) {
            return $this->whereAggregate($relation, $column, $function, 'null', null, 'or', $not);
        });
        Builder::macro('whereAggregateNotNull', function ($relation, $column, $function = null, string $boolean = 'and') {
            return $this->whereAggregate($relation, $column, $function, 'notNull', null, $boolean);
        });
        Builder::macro('orWhereAggregateNotNull', function ($relation, $column, $function = null) {
            return $this->whereAggregate($relation, $column, $function, 'notNull', null, 'or');
        });

        // Sum
        Builder::macro('withWhereRelationSum', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'sum', $operator, $value, $boolean, $not, true);
        });

        Builder::macro('whereRelationSum', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'sum', $operator, $value, $boolean, $not, false);
        });


        // Avg
        Builder::macro('withWhereRelationAvg', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'avg', $operator, $value, $boolean, $not, true);
        });

        Builder::macro('whereRelationAvg', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'avg', $operator, $value, $boolean, $not, false);
        });


        // Max
        Builder::macro('withWhereRelationMax', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'max', $operator, $value, $boolean, $not, true);
        });

        Builder::macro('whereRelationMax', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'max', $operator, $value, $boolean, $not, false);
        });

        // Min
        Builder::macro('withWhereRelationMin', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'min', $operator, $value, $boolean, $not, true);
        });

        Builder::macro('whereRelationMin', function ($relation, $column, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, $column, 'min', $operator, $value, $boolean, $not, false);
        });


        // Count
        Builder::macro('withWhereRelationCount', function ($relation, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, '*', 'count', $operator, $value, $boolean, $not, true);
        });

        Builder::macro('whereRelationCount', function ($relation, $operator = '=', $value = null, string $boolean = 'and', bool $not = false) {
            return $this->withWhereAggregate($relation, '*', 'count', $operator, $value, $boolean, $not, false);
        });


    }

}
