<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums;

use ArchTech\Enums\Comparable;

enum FilterMatchMode: string
{
    use Comparable;

    case containsAll = 'containsAll';
    case containsAny = 'containsAny';
    case contains = 'contains';
    case isNull = 'isNull';
    case isNotNull = 'isNotNull';
    case equals = 'equals';
    case notEquals = 'notEquals';
    case between = 'between';
    case notBetween = 'notBetween';
    case whereIn = 'whereIn';
    case whereNotIn = 'whereNotIn';
    case notContains = 'notContains';
    case startsWith = 'startsWith';
    case endsWith = 'endsWith';
    case dateIs = 'dateIs';
    case dateIsNot = 'dateIsNot';
    case dateBefore = 'dateBefore';
    case dateAfter = 'dateAfter';
    case dateLte = 'dateLte';
    case dateGte = 'dateGte';
    case dateIsOrBefore = 'dateIsOrBefore';
    case dateIsOrAfter = 'dateIsOrAfter';
    case dateBetween = 'dateBetween';
    case dateNotBetween = 'dateNotBetween';
    case lessThan = 'lt';
    case lessThanOrEquals = 'lte';
    case greaterThan = 'gt';
    case greaterThanOrEquals = 'gte';
}
