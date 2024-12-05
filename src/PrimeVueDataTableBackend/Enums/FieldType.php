<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums;

enum FieldType: string
{
    case main = 'main';
    case json = 'json';
    case jsonArray = 'json_array';
    case mainCount = 'main_count';
    case relationMany = 'relation_many';
    case relation = 'relation';
    case relationCount = 'relation_count';
    case custom = 'custom';
}
