<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums;

use ArchTech\Enums\Comparable;

enum FieldType: string
{
    use Comparable;
    case main = 'main';
    case json = 'json';
    case jsonArray = 'json_array';
    case mainCount = 'main_count';
    case relationMany = 'relation_many';
    case relation = 'relation';
    case relationCount = 'relation_count';
    case relationAggregate = 'relation_aggregate';
    case custom = 'custom';
}
