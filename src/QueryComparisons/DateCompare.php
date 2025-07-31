<?php

namespace HassanDomeDenea\HddLaravelHelpers\QueryComparisons;

use DateTimeInterface;
use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\IdentifiesDriver;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;
use Tpetry\QueryExpressions\Value\Value;

class DateCompare implements ConditionExpression
{
    use IdentifiesDriver;
    use StringizeExpression;

    /**
     * @param string|Expression $mainColumn
     * @param string|Expression $operator
     * @param string|DateTimeInterface|Expression|null $value
     * @param bool $autoCastToValues If this is ture, all values will be transformed to new Value, otherwise they will be as passed. Turn it of if you want to use column names.
     */
    public function __construct(
        private readonly string|Expression                        $mainColumn,
        private readonly string|DateTimeInterface|Expression      $operator = '>',
        private readonly null|string|DateTimeInterface|Expression $value = null,
        public readonly bool                                      $autoCastToValues = true,

    )
    {
    }

    public function getValue(Grammar $grammar)
    {
        $mainColumn = $this->stringize($grammar, $this->mainColumn);
        $localOperator = $this->operator;
        $localValue = $this->value;
        if (!$localValue) {
            $localOperator = '=';
            $localValue = $this->operator;
        }
        $formattedValue = $this->stringize($grammar, $localValue instanceof DateTimeInterface ? new Value($localValue->format('Y-m-d')) : ($this->autoCastToValues ? ($localValue instanceof Expression ? $localValue : new Value($localValue)) : $localValue));
        $operator = $localOperator;
        $mainColumn = match ($this->identify($grammar)) {
            'sqlite' => "strftime('%Y-%m-%d', $mainColumn)",
            'mysql' => "DATE($mainColumn)",
            'pgsql' => "$mainColumn::date",
            default => $mainColumn
        };
        return "($mainColumn $operator $formattedValue)";
    }
}
