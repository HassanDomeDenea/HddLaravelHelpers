<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use HassanDomeDenea\HddLaravelHelpers\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HasDeletableCheck
{

    public function canBeDeleted(): bool|string
    {
        return true;
    }

    public function customDeleteLogic(): void
    {
        $this->delete();
    }

    /**
     * @throws ValidationException
     */
    public function checkAndDelete(): void
    {
        if (
            ($errorMsg = $this->canBeDeleted()) === true
        ) {
            $this->customDeleteLogic();
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
                        $collection->each(/**
                         * @param BaseModel $model
                         * @return void
                         */ function (Model $model) use (
                            $validator,
                        ) {
                            if (
                                ($errorMsg = $model->canBeDeleted()) === true
                                && (!$validator || ($errorMsg = $validator($model)) === true)
                            ) {
                                $model->customDeleteLogic();
                            } else {
                                throw ValidationException::withMessages(['ids' => [$errorMsg]]);
                            }
                        });
                    });
            DB::commit();

        } catch (Throwable $e) {
            throw ValidationException::withMessages(['ids' => [$e->getMessage() ?: __('Error Occurred')]]);
        }
    }
}
