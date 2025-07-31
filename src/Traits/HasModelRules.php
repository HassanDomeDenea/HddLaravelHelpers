<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use HassanDomeDenea\HddLaravelHelpers\Rules\EnsureEveryIdExistsRule;
use HassanDomeDenea\HddLaravelHelpers\Rules\ModelExistsRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

trait HasModelRules
{
    public static function getTableName(): string
    {
        return (new static)->getTable();
    }

    public static function getTablePrimaryKey(): string
    {
        return (new static)->getKeyName();
    }

    public static function uniqueRule(string $columnName = 'name'): Unique
    {
        return Rule::unique(static::getTableName(), $columnName)
            ->whereNull('deleted_at');
    }

    public static function existsRule(string $columnName = 'id'): Exists
    {
        return Rule::exists(static::getTableName(), $columnName)
            ->whereNull('deleted_at');
    }

    public static function modelExistsRule(string $columnName = 'id'): ModelExistsRule
    {
        return new ModelExistsRule(static::class, $columnName);
    }

    public static function existsMultiRule(string $columnName = 'id'): EnsureEveryIdExistsRule
    {
        return new EnsureEveryIdExistsRule(static::class, $columnName);
    }
}
