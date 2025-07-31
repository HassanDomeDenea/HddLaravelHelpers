<?php

namespace HassanDomeDenea\HddLaravelHelpers\QueryComparisons;

use DateTimeInterface;
use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Tpetry\QueryExpressions\Concerns\IdentifiesDriver;
use Tpetry\QueryExpressions\Concerns\StringizeExpression;
use Tpetry\QueryExpressions\Value\Value;

class DateBetween implements ConditionExpression
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
        private readonly null|string|DateTimeInterface|Expression $valueMin = null,
        private readonly null|string|DateTimeInterface|Expression $valueMax = null,
        public readonly bool                                      $autoCastToValues = true,

    )
    {
    }

    public function getValue(Grammar $grammar)
    {
        $mainColumn = $this->stringize($grammar, $this->mainColumn);

        $formattedValueMin = $this->stringize($grammar, $this->valueMin instanceof DateTimeInterface ? new Value($this->valueMin->format('Y-m-d')) : ($this->autoCastToValues ? ($this->valueMin instanceof Expression ? $this->valueMin : new Value($this->valueMin)) : $this->valueMin));
        $formattedValueMax = $this->stringize($grammar, $this->valueMax instanceof DateTimeInterface ? new Value($this->valueMax->format('Y-m-d')) : ($this->autoCastToValues ? ($this->valueMax instanceof Expression ? $this->valueMax : new Value($this->valueMax)) : $this->valueMax));
        $mainColumn = match ($this->identify($grammar)) {
            'sqlite' => "strftime('%Y-%m-%d', $mainColumn)",
            'mysql' => "DATE($mainColumn)",
            'pgsql' => "$mainColumn::date",
            default => $mainColumn
        };
        return "($mainColumn between $formattedValueMin and $formattedValueMax)";
    }
}
