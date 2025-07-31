<?php

use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\In;
use Tpetry\QueryExpressions\Operator\Comparison\Equal;
use Tpetry\QueryExpressions\Value\Value;

it('in sql is proper', function () {
    $expr = new In('role', ['admin', 'editor']);

    $grammar = DB::connection()->getQueryGrammar();

    $sql = $expr->getValue($grammar);
    $bindings = $expr->getBindings();

    $this->assertSame('"role" in (?, ?)', $sql);
    $this->assertSame(['admin', 'editor'], $bindings);
});

it('test', function () {


});
