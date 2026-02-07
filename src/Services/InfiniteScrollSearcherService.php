<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Services;

use DB;
use HassanDomeDenea\HddLaravelHelpers\Data\InfiniteScrollResponseData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\Helpers\SearchArabicNamesUsingRegexp;
use HassanDomeDenea\HddLaravelHelpers\Requests\InfiniteScrollRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Resource;
use Spatie\QueryBuilder\QueryBuilder;

class InfiniteScrollSearcherService
{
    protected string|array $name;

    protected string $trimmedName;

    protected int $offset = 0;

    protected int $_limit = 25;

    protected bool $_includeId = false;

    protected bool $onlyId = false;

    protected bool $multipleIds = false;

    protected bool $_allowEmpty = true;

    /**
     * @var class-string<Data>|null
     */
    protected ?string $_dataClass = null;

    protected string|array $_idColumn = 'id';

    public function __construct(InfiniteScrollRequest $request, public Builder|QueryBuilder $modelQuery, public array $filterColumns = ['name' => 'regexp'], public string $filtersBoolean = 'or', ?string $defaultOrderColumn = null, string $defaultOrderDirection = 'asc')
    {
        $this->name = $request->validated('name', '') ?: '';
        $this->offset = $request->integer('offset');
        $this->_limit = $request->integer('limit', 25);
        $this->onlyId = $request->boolean('only_id');
        $this->multipleIds = $request->boolean('multiple_ids');
        $this->_idColumn = $request->string('id_field', 'id')->toString();
        if ($request->order_by) {
            $this->modelQuery->orderBy($request->order_by, $request->order_by_direction ?: 'asc');
        } elseif ($defaultOrderColumn) {
            $this->modelQuery->orderBy($defaultOrderColumn, $defaultOrderDirection);
        } else {
            $this->modelQuery->orderBy(array_key_first($this->filterColumns));
        }
    }

    public function includeId(bool $value): self
    {
        $this->_includeId = $value;

        return $this;
    }

    public function setIdColumn(string|array $value): self
    {
        $this->_idColumn = $value;

        return $this;
    }

    /**
     * @param  class-string<Data|resource>|null  $value
     * @return $this
     */
    public function setDataClass(?string $value): self
    {
        $this->_dataClass = $value;

        return $this;
    }

    public function proceed(): JsonResponse
    {
        if (filled($this->name) || $this->_allowEmpty) {
            if ($this->onlyId) {
                $this->modelQuery->where(function (Builder $query) {
                    if ($this->multipleIds) {
                        if (is_array($this->_idColumn)) {
                            foreach ($this->_idColumn as $column) {
                                $query->whereIn($column, $this->name, boolean: 'or');
                            }
                        } else {
                            $query->whereIn($this->_idColumn, $this->name);
                        }
                    } else {
                        $this->trimmedName = mb_trim($this->name);
                        if (is_array($this->_idColumn)) {
                            foreach ($this->_idColumn as $column) {
                                $query->where($column, $this->trimmedName, boolean: 'or');
                            }
                        } else {
                            $query->where($this->_idColumn, $this->trimmedName);
                        }
                    }
                });
            } else {
                $this->trimmedName = mb_trim($this->name);
                $filtersBoolean = $this->filtersBoolean;
                $this->modelQuery->where(function (Builder $query) use ($filtersBoolean) {
                    if ($this->_includeId) {
                        if (is_array($this->_idColumn)) {
                            foreach ($this->_idColumn as $column) {
                                $query->where($column, $this->trimmedName, boolean: $filtersBoolean);
                            }
                        } else {
                            $query->where($this->_idColumn, $this->trimmedName, boolean: $filtersBoolean);
                        }
                    }
                    foreach ($this->filterColumns as $columnName => $columnMethod) {
                        switch ($columnMethod) {
                            case 'regexp':
                                $query->where($columnName, 'regexp', SearchArabicNamesUsingRegexp::convertNameToRegexp($this->name), boolean: $filtersBoolean);
                                break;
                            case 'like':
                                $query->where($columnName, 'like', "%$this->name%", boolean: $filtersBoolean);
                                break;
                            case 'equals':
                                $query->where($columnName, '=', $this->trimmedName, boolean: $filtersBoolean);
                                break;
                            case 'containsAny':
                                $query->where(function (Builder $q) use ($columnName) {
                                    $keys = explode(' ', $this->trimmedName);
                                    foreach ($keys as $key) {
                                        $q->where($columnName, 'like', "%$key%", boolean: 'or');
                                    }
                                }, boolean: $filtersBoolean);
                                break;
                            default:
                                $query->where($columnMethod, 'like', "%$this->name%", boolean: $filtersBoolean);
                        }
                    }
                });

            }
            if (empty($this->modelQuery->getGroupBy())) {
                $total = $this->modelQuery->count();
            } else {
                $total = DB::table(DB::raw("({$this->modelQuery->toRawSql()}) as sub"))->count();
            }
            $items = $this->modelQuery
                ->offset($this->offset)
                ->limit($this->_limit)
                ->get();
        } else {
            $items = [];
            $total = 0;
        }

        return ApiResponse::successResponse(InfiniteScrollResponseData::from([
            'items' => $this->_dataClass ? $this->_dataClass::collect($items) : $items,
            'total' => $total,
        ]));
    }
}
