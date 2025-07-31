<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasReordering
{

    /**
     * Get the name of the column used for ordering.
     *
     * @return string
     */
    public function getOrderColumnName(): string
    {
        return 'order_sequence';
    }

    /**
     * Get the name of the column(s) used for scoping the ordering.
     *
     * @return array
     */
    public function getOrderScopedColumns(): array
    {
        return [];
    }

    public function initializeHasReordering()
    {
        if (!isset($this->casts[$this->getOrderColumnName()])) {
            $this->casts[$this->getOrderColumnName()] = 'integer';
        }
    }

    protected static function bootHasReordering(): void
    {
        static::addGlobalScope('ordered', function (Builder $builder) {
            $builder->ordered();
        });

        static::creating(function ($model) {
            if (is_null($model->{$model->getOrderColumnName()})) {
                $query = static::query();

                // Apply scope conditions if they exist
                foreach ($model->getOrderScopedColumns() as $column) {
                    if (isset($model->{$column})) {
                        $query->where($column, $model->{$column});
                    }
                }

                // Get the maximum order value and add 1
                $maxOrder = $query->max($model->getOrderColumnName()) ?? 0;
                $model->{$model->getOrderColumnName()} = $maxOrder + 1;
            }
        });
    }


    /**
     * Scope a query to order by the order column.
     * If scope columns are defined, it will order it by those first.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        $scopeColumns = $this->getOrderScopedColumns();

        // First order by scope columns if any
        foreach ($scopeColumns as $column) {
            $query->orderBy($column);
        }

        // Then order by the order column
        return $query->orderBy($this->getOrderColumnName());
    }

    /**
     * Move a model from one position to another.
     *
     * @param int $fromOrder
     * @param int $toOrder
     * @param array|null $scopeValues
     * @return bool
     */
    public static function reorderSequence(int $fromOrder, int $toOrder, ?array $scopeValues = null): bool
    {
        if ($fromOrder === $toOrder) {
            return true; // No change needed
        }

        $model = new static;
        $orderColumn = $model->getOrderColumnName();
        $scopeColumns = $model->getOrderScopedColumns();
        try {

            return DB::transaction(function () use ($model, $orderColumn, $fromOrder, $toOrder, $scopeColumns, $scopeValues) {
                $query = static::where($orderColumn, $fromOrder);

                // Apply scope conditions if provided
                if (!empty($scopeColumns)) {
                    foreach ($scopeColumns as $column) {
                        if (isset($scopeValues[$column])) {
                            $query->where($column, $scopeValues[$column]);
                        } else {
                            return false;
                        }
                    }
                }

                $fromModel = $query->first();

                if (!$fromModel) {
                    return false;
                }

                // Build the base query for reordering with scope conditions
                $baseQuery = static::query();

                // Apply scope conditions to the base query
                if (!empty($scopeColumns)) {
                    foreach ($scopeColumns as $column) {
                        if (isset($scopeValues[$column])) {
                            $baseQuery->where($column, $scopeValues[$column]);
                        } else {
                            return false;
                        }
                    }
                }

                if ($fromOrder < $toOrder) {
                    // Moving down: decrement items in between
                    $baseQuery->where($orderColumn, '>', $fromOrder)
                        ->where($orderColumn, '<=', $toOrder)
                        ->decrement($orderColumn);
                } else {
                    // Moving up: increment items in between
                    $baseQuery->where($orderColumn, '<', $fromOrder)
                        ->where($orderColumn, '>=', $toOrder)
                        ->increment($orderColumn);
                }

                // Set the item to its new position
                $fromModel->{$orderColumn} = $toOrder;
                $fromModel->save();

                return true;
            });

        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return false;
        }
    }
}
