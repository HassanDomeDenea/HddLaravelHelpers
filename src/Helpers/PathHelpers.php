<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

class PathHelpers
{
    public static function getRelativePath(string $from, string $to): string
    {
        $from = explode(DIRECTORY_SEPARATOR, rtrim(dirname($from), DIRECTORY_SEPARATOR));
        $to = explode(DIRECTORY_SEPARATOR, rtrim($to, DIRECTORY_SEPARATOR));

        while (!empty($from) && !empty($to) && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('..' . DIRECTORY_SEPARATOR, count($from)) . implode(DIRECTORY_SEPARATOR, $to);
    }
}
