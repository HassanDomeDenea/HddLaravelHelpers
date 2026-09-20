<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums;

use ArchTech\Enums\Comparable;

enum SortAs: string
{
    use Comparable;
    case natural = 'natural';
}
