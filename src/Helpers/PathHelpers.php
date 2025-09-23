<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Throwable;

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
     * @return class-string<Data>|false
     */
    public static function getDataClassFromModelClass(string $modelClassName, string $prefix = ''): string|false
    {

        return Cache::remember('HDD-' . $modelClassName . 'DataClass' . $prefix, app()->isProduction() ? CarbonInterval::day() : 1, function () use ($modelClassName, $prefix) {
            $isolateInSubFolders = config()->boolean('hdd-laravel-helpers.data-classes.isolate-in-subfolders', true);
            $baseModelClassName = class_basename($modelClassName);

            $expectedClass = str($modelClassName)->replace('\Models\\', '\Data\\' . ($isolateInSubFolders ? $baseModelClassName . '\\' : ''))
                ->when(filled($prefix), fn($str) => $str->replaceLast($baseModelClassName, $prefix . $baseModelClassName));
            $expectedClassWithData = $expectedClass->append('Data');

            if (class_exists($expectedClassWithData)) {
                return $expectedClassWithData;
            }
            if (class_exists($expectedClass))
                return $expectedClass;

            return false;
        });
    }

    /**
     * @param class-string<Model> $modelClassName
     * @return class-string<Data>|false
     */
    public static function getCreateActionClassFromModelClass(string $modelClassName): string|false
    {
        return Cache::remember('HDD-' . $modelClassName . 'CreateActionClass', app()->isProduction() ? CarbonInterval::day() : 1, function () use ($modelClassName) {
            $isolateInSubFolders = config('hdd-laravel-helpers.data-classes.isolate-in-subfolders', true);
            $baseModelClassName = class_basename($modelClassName);

            $expectedClass = str($modelClassName)->replace('\Models\\', '\Actions\\' . ($isolateInSubFolders ? "$baseModelClassName\\" : '') . 'Create');
            $expectedClassWithAction = $expectedClass->append('Action');
            if (class_exists($expectedClassWithAction)) {
                return $expectedClassWithAction;
            }
            if (class_exists($expectedClass))
                return $expectedClass;

            return false;
        });
    }

    /**
     * @param class-string<Model> $modelClassName
     * @return class-string<Data>|false
     */
    public static function getUpdateActionClassFromModelClass(string $modelClassName): string|false
    {
        return Cache::remember('HDD-' . $modelClassName . 'UpdateActionClass', app()->isProduction() ? CarbonInterval::day() : 1, function () use ($modelClassName) {

            $isolateInSubFolders = config('hdd-laravel-helpers.data-classes.isolate-in-subfolders', true);
            $baseModelClassName = class_basename($modelClassName);

            $expectedClass = str($modelClassName)->replace('\Models\\', '\Actions\\' . ($isolateInSubFolders ? "$baseModelClassName\\" : '') . 'Update');

            $expectedClassWithAction = $expectedClass->append('Action');
            if (class_exists($expectedClassWithAction)) {
                return $expectedClassWithAction;
            }
            if (class_exists($expectedClass))
                return $expectedClass;

            return false;
        });
    }

    public static function getActionClassAttributeType(?string $actionClassName, int $parameterIndex = 0): ?string
    {
        if (empty($actionClassName)) {
            return null;
        }
        try {
            return (new ReflectionClass($actionClassName))
                ->getMethod('handle')->getParameters()[$parameterIndex]->getType()->getName() ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
