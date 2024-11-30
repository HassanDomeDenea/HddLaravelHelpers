<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\Enums;

enum FilterMatchMode: string
{
    case containsAll = 'containsAll';
    case containsAny = 'containsAny';
    case contains = 'contains';
    case isNull = 'isNull';
    case isNotNull = 'isNotNull';
    case equals = 'equals';
    case notEquals = 'notEquals';
    case whereIn = 'whereIn';
    case whereNotIn = 'whereNotIn';
    case notContains = 'notContains';
    case startsWith = 'startsWith';
    case endsWith = 'endsWith';
    case dateIs = 'dateIs';
    case dateIsNot = 'dateIsNot';
    case dateBefore = 'dateBefore';
    case dateAfter = 'dateAfter';
    case dateIsOrBefore = 'dateIsOrBefore';
    case dateIsOrAfter = 'dateIsOrAfter';
    case lessThan = 'lt';
    case lessThanOrEquals = 'lte';
    case greaterThan = 'gt';
    case greaterThanOrEquals = 'gte';
}
