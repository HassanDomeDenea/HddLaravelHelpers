<?php

namespace HassanDomeDenea\HddLaravelHelpers\QueryComparisons;

use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;
use Tpetry\QueryExpressions\Value\Number;
use Tpetry\QueryExpressions\Value\Value;

class In implements ConditionExpression
{
    use StringizeExpression;

    /**
     * @param string|Expression|Builder $mainColumn
     * @param (string|Expression)[] $values
     * @param bool $not
     * @param bool $autoCastToValues If this is ture, all values will be transformed to new Value, otherwise they will be as passed. Turn it of if you want to use column names.
     */
    public function __construct(
        private readonly string|Expression|Builder $mainColumn,
        private readonly array                     $values,
        public readonly bool                       $not = false,
        public readonly bool                       $autoCastToValues = true,

    )
    {

    }

    public function getValue(Grammar $grammar)
    {
        $mainColumn = $this->mainColumn instanceof Builder ? "(" . $this->mainColumn->toRawSql() . ")" : $this->stringize($grammar, $this->mainColumn);

        $values = array_map(fn($v) => $this->stringize($grammar, $this->autoCastToValues ? ($v instanceof Expression ? $v : (new Value($v))) : $v), is_array($this->values) ? $this->values : [$this->values]);
        $valuesAsString = implode(', ', $values);
        $operator = $this->not ? 'not in' : 'in';
        return "($mainColumn $operator ($valuesAsString))";
    }
}
