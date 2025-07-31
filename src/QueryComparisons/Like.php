<?php

namespace HassanDomeDenea\HddLaravelHelpers\QueryComparisons;

use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;
use Tpetry\QueryExpressions\Operator\OperatorExpression;
use Tpetry\QueryExpressions\Value\Number;
use Tpetry\QueryExpressions\Value\Value;

class Like implements ConditionExpression
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
        private readonly mixed                     $value,
        public readonly bool                       $matchAnyStart = true,
        public readonly bool                       $matchAnyEnd = true,
        public readonly bool                       $not = false,
        public readonly bool                       $autoCastToValues = true,

    )
    {

    }

    public function getValue(Grammar $grammar): string
    {
        $column = $this->stringize($grammar, $this->mainColumn);
        $value = $this->stringize($grammar, $this->autoCastToValues ? ($this->value instanceof Expression ? $this->value : (new Value($this->value))) : $this->value);


        $startingStr = $this->matchAnyStart ? '%' : '';
        $endingStr = $this->matchAnyEnd ? '%' : '';
        $contacted = str($value)->trim("'")->prepend("'" . $startingStr)->append($endingStr . "'")->toString();
        $not = $this->not ? 'not' : '';
        return "($column $not like $contacted)";
    }
}
