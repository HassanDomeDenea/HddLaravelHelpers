<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums;

enum FieldType: string
{
    case main = 'main';
    case json = 'json';
    case mainCount = 'main_count';
    case relation = 'relation';
    case relationCount = 'relation_count';
    case custom = 'custom';
}
