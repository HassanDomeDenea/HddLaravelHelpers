<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

class StringHelpers
{
    public static function parseAggregateString(string $string): ?array {
        $allowed = ['sum', 'avg', 'min', 'max', 'count'];

        // Build a regex: _(sum|avg|...)_
        $pattern = '/_(sum|avg|min|max|count)_/';

        if (preg_match($pattern, $string, $matches, PREG_OFFSET_CAPTURE)) {
            $aggregate = $matches[1][0]; // The aggregate word
            /** @var int $pos */
            $pos = $matches[0][1]; // Start position of match
            // Before the _aggregate_ => relation
            $relation = substr($string, 0, $pos);

            // After the _aggregate_ => column
            $column = substr($string, $pos + strlen("_{$aggregate}_"));

            return [$relation, $aggregate, $column];
        }

        return null; // Not matched
    }
}
