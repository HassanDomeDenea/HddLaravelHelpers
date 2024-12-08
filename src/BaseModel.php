<?php

namespace HassanDomeDenea\HddLaravelHelpers;

use HassanDomeDenea\HddLaravelHelpers\Requests\StoreManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\UpdateManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Rules\EnsureEveryIdExistsRule;
use HassanDomeDenea\HddLaravelHelpers\Rules\ModelExistsRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;
use Throwable;

class BaseModel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    public static function getTableName(): string
    {
        return (new static)->getTable();
    }

    protected $guarded = [];

    public function canBeDeleted(): bool|string
    {
        return true;
    }

    /**
     * @throws ValidationException
     */
    public function checkAndDelete(): void
    {
        if (
            ($errorMsg = $this->canBeDeleted()) === true
        ) {
            $this->delete();
        } else {
            throw ValidationException::withMessages(['id' => [$errorMsg]]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function checkAndDeleteMany(array $ids, ?callable $validator = null): void
    {
        try {
            DB::beginTransaction();
            static::whereIn('id', $ids)
                ->chunk(1000,
                    function (Collection $collection) use ($validator) {
                        $collection->each(function (BaseModel $model) use (
                            $validator,
                        ) {
                            if (
                                ($errorMsg = $model->canBeDeleted()) === true
                                && (!$validator || ($errorMsg = $validator($model)) === true)
                            ) {
                                $model->delete();
                            } else {
                                throw ValidationException::withMessages(['ids' => [$errorMsg]]);
                            }
                        });
                    });
            DB::commit();

        } catch (Throwable $e) {
            ray($e);
            throw ValidationException::withMessages(['ids' => [$e->getMessage() ?: __('Error Occurred')]]);
        }
    }

    public static function checkAndCreateMany(string $formRequestClassName, StoreManyRequest|array|null $request = null, ?callable $customCreateFunction = null): array
    {
        try {
            $ids = [];
            DB::transaction(function () use ($customCreateFunction, $formRequestClassName, $request, &$ids) {
                if (is_array($request)) {
                    $dataList = $request;
                } else {
                    if (!$request) {
                        $request = request();
                    }
                    $dataList = $request->data;
                }
                foreach ($dataList as $item) {
                    $itemRequest = new $formRequestClassName(request: $item);
                    $itemRequest->merge($item);
                    $validator = Validator::make($item, $itemRequest->rules(),
                        $itemRequest->messages(), $itemRequest->attributes());
                    $validator->validate();
                    if ($customCreateFunction) {
                        $model = $customCreateFunction($item);
                    } else {
                        $model = static::create($item);
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

    public static function checkAndUpdateMany(string $formRequestClassName, UpdateManyRequest|array|null $request = null, ?callable $customUpdateMethod = null): array
    {
        try {
            $ids = [];
            DB::transaction(function () use ($customUpdateMethod, $formRequestClassName, $request, &$ids) {
                if (is_array($request)) {
                    $dataList = $request;
                } else {
                    if (!$request) {
                        $request = request();
                    }
                    $dataList = $request->data;
                }
                $modelBindingName = Str::snake(class_basename(static::class));
                $modelsList = static::query()->findMany(Arr::pluck($dataList, 'id'));
                foreach ($dataList as $key => $item) {
                    $id = $item['id'] ?? $key;
                    $model = $modelsList->where('id', $id)->firstOrFail();
                    $itemRequest = new $formRequestClassName(request: $item);
                    $itemRequest->merge($item);
                    $itemRequest->{$modelBindingName} = $model;
                    $validator = Validator::make($item, $itemRequest->rules(),
                        $itemRequest->messages(), $itemRequest->attributes());
                    $validator->validate();
                    if ($customUpdateMethod) {
                        $customUpdateMethod($model, $item);
                    } else {
                        $model->update($item);
                    }
                    $ids[] = $model->id;
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
        return new EnsureEveryIdExistsRule(static::class,$columnName);
    }
}
