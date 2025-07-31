<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use HassanDomeDenea\HddLaravelHelpers\Requests\StoreManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\UpdateManyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Throwable;

trait HasCreateAndDeleteMany
{

    public static function checkAndCreateMany(string $formRequestOrDataClassName, StoreManyRequest|array|null $request = null, ?callable $customCreateFunction = null): array
    {
        try {
            $ids = [];
            DB::transaction(function () use ($customCreateFunction, $formRequestOrDataClassName, $request, &$ids) {
                if (is_array($request)) {
                    $dataList = $request;
                } else {
                    if (!$request) {
                        $request = request();
                    }
                    $dataList = $request->data;
                }
                foreach ($dataList as $item) {
                    if (class_exists($formRequestOrDataClassName)) {
                        if (is_subclass_of($formRequestClassName = $formRequestOrDataClassName, FormRequest::class)) {
                            $itemRequest = new $formRequestClassName(request: $item);
                            $itemRequest->merge($item);
                            $validator = Validator::make($item, $itemRequest->rules(),
                                $itemRequest->messages(), $itemRequest->attributes());
                            $validator->validate();
                            $validated = $validator->validated();
                        } else if (is_subclass_of($formDataClassName = $formRequestOrDataClassName, Data::class)) {
                            $validated = $formDataClassName::validate($item);
                        } else {
                            $validated = $item;
                        }
                    } else {
                        $validated = $item;
                    }

                    if ($customCreateFunction) {
                        $model = $customCreateFunction($validated);
                    } else {
                        $model = static::create($validated);
                    }
                    if ($model) {
                        $ids[] = $model->id;
                    }
                }
            });

            return $ids;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'data' => [
                    __('Error Occurred'), $e->getMessage(),
                ],
            ]);
        }
    }

    public static function checkAndUpdateMany(string $formRequestOrDataClassName, UpdateManyRequest|array|null $request = null, ?callable $customUpdateMethod = null): array
    {
        try {
            $ids = [];
            DB::transaction(function () use ($customUpdateMethod, $formRequestOrDataClassName, $request, &$ids) {
                if (is_array($request)) {
                    $dataList = $request;
                } else {
                    if (!$request) {
                        $request = request();
                    }
                    $dataList = $request->data;
                }
                $modelBindingName = Str::snake(class_basename(static::class));
                $primaryKeyName = static::getTablePrimaryKey();
                $modelsList = static::query()->findMany(Arr::pluck($dataList, $primaryKeyName));
                foreach ($dataList as $key => $item) {
                    $id = $item[$primaryKeyName] ?? $key;
                    $model = $modelsList->where($primaryKeyName, $id)->firstOrFail();
                    if (class_exists($formRequestOrDataClassName)) {
                        if (is_subclass_of($formRequestClassName = $formRequestOrDataClassName, FormRequest::class)) {
                            /** @var FormRequest $itemRequest */
                            $itemRequest = new $formRequestClassName(request: $item);
                            $itemRequest->merge($item);
                            $itemRequest->{$modelBindingName} = $model;
                            $validator = Validator::make($item, $itemRequest->rules(),
                                $itemRequest->messages(), $itemRequest->attributes());
                            $validated = $validator->validated();
                        } else if (is_subclass_of($formDataClassName = $formRequestOrDataClassName, Data::class)) {
                            $validated = $formDataClassName::validate($item);
                        } else {
                            $validated = $item;
                        }
                    } else {
                        $validated = $item;
                    }
                    if ($customUpdateMethod) {
                        $customUpdateMethod($model, $validated);
                    } else {
                        $model->update($validated);
                    }
                    $ids[] = $model[$primaryKeyName];
                }
            });

            return $ids;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (config('app.env') !== 'production') {
                throw ValidationException::withMessages([
                    'data' => [
                        __('Error Occurred'),
                        $e->getMessage() ?: class_basename($e),
                    ],
                ]);
            } else {
                Log::error($e->getMessage(), $e->getTrace());
                throw ValidationException::withMessages([
                    'data' => [
                        __('Error Occurred'),
                    ],
                ]);
            }
        }
    }
}
