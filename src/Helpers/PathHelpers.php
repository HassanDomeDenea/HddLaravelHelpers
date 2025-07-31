<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

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


    /**
     * @param class-string<Model> $modelClassName
     * @return class-string<Data>
     */
    public static function getDataClassFromModelClass(string $modelClassName, string $prefix = ''): string|false
    {
        $expectedClass = str($modelClassName)->replace('\Models\\', '\Data\\')
            ->replace(class_basename($modelClassName), $prefix . class_basename($modelClassName));

        $expectedClassWithData = $expectedClass->append('Data');
        if (class_exists($expectedClassWithData)) {
            return $expectedClassWithData;
        }
        if (class_exists($expectedClass))
            return $expectedClass;

        return false;
    }

    /**
     * @param class-string<Model> $modelClassName
     * @return class-string<Data>
     */
    public static function getCreateActionClassFromModelClass(string $modelClassName): string|false
    {
        $expectedClass = str($modelClassName)->replace('\Models\\', '\Actions\\Create');

        $expectedClassWithAction = $expectedClass->append('Action');
        if (class_exists($expectedClassWithAction)) {
            return $expectedClassWithAction;
        }
        if (class_exists($expectedClass))
            return $expectedClass;

        return false;
    }

    /**
     * @param class-string<Model> $modelClassName
     * @return class-string<Data>
     */
    public static function getUpdateActionClassFromModelClass(string $modelClassName): string|false
    {
        $expectedClass = str($modelClassName)->replace('\Models\\', '\Actions\\Update');

        $expectedClassWithAction = $expectedClass->append('Action');
        if (class_exists($expectedClassWithAction)) {
            return $expectedClassWithAction;
        }
        if (class_exists($expectedClass))
            return $expectedClass;

        return false;
    }
}
