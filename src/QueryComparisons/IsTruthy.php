<?php

namespace HassanDomeDenea\HddLaravelHelpers\QueryComparisons;

use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;
use Tpetry\QueryExpressions\Operator\OperatorExpression;
use Tpetry\QueryExpressions\Value\Number;
use Tpetry\QueryExpressions\Value\Value;

class IsTruthy implements ConditionExpression
{
    use StringizeExpression;

    /**
     * @param string|Expression|Builder $mainColumn
     * @param mixed $value
     * @param bool $not
     * @param bool $autoCastToValues If this is ture, all values will be transformed to new Value, otherwise they will be as passed. Turn it of if you want to use column names.
     * @param bool $matchAnyStart
     * @param bool $matchAnyEnd
     * @param bool $not
     */
    public function __construct(
        private readonly string|Expression|Builder $mainColumn,
        private readonly bool                      $value = true,

    )
    {

    }

    public function getValue(Grammar $grammar): string
    {
        $column = $this->stringize($grammar, $this->mainColumn);
        $value = "is true";
        if (!$this->value) {
            $value = "is false";
        }
        return "($column $value)";
    }
}
