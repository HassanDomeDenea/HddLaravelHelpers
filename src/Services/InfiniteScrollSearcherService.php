<?php

namespace HassanDomeDenea\HddLaravelHelpers\Services;

use HassanDomeDenea\HddLaravelHelpers\Data\InfiniteScrollResponseData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\Helpers\SearchArabicNamesUsingRegexp;
use HassanDomeDenea\HddLaravelHelpers\Requests\InfiniteScrollRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Data;

class InfiniteScrollSearcherService
{
    protected string $name;

    protected string $trimmedName;

    protected int $offset = 0;

    protected int $_limit = 25;

    protected bool $_includeId = false;

    protected bool $onlyId = false;

    protected bool $_allowEmpty = true;

    /**
     * @var class-string<Data>|null
     */
    protected ?string $_dataClass = null;

    protected string|array $_idColumn = 'id';

    public function __construct(InfiniteScrollRequest $request, public Builder $modelQuery, public array $filterColumns = ['name' => 'regexp'])
    {
        $this->name = $request->validated('name', '') ?: '';
        $this->offset = $request->integer('offset', 0);
        $this->_limit = $request->integer('limit', 25);
        $this->onlyId = $request->boolean('onlyId', false);
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
     * @param  class-string<Data>|null  $value
     * @return $this
     */
    public function setDataClass(?string $value): self
    {
        $this->_dataClass = $value;

        return $this;
    }

    public function proceed(): JsonResponse
    {
        if (! empty($this->name) || $this->_allowEmpty) {
            $this->trimmedName = trim($this->name);
            if ($this->onlyId) {
                $this->modelQuery->where(function (Builder $query) {
                    if (is_array($this->_idColumn)) {
                        foreach ($this->_idColumn as $column) {
                            $query->orWhere($column, $this->trimmedName);
                        }
                    } else {
                        $query->where($this->_idColumn, $this->trimmedName);
                    }
                });
            } else {
                $this->modelQuery->where(function (Builder $query) {
                    if ($this->_includeId) {
                        if (is_array($this->_idColumn)) {
                            foreach ($this->_idColumn as $column) {
                                $query->orWhere($column, $this->trimmedName);
                            }
                        } else {
                            $query->where($this->_idColumn, $this->trimmedName);
                        }
                    }
                    foreach ($this->filterColumns as $columnName => $columnMethod) {
                        switch ($columnMethod) {
                            case 'regexp':
                                $query->orWhere($columnName, 'regexp', SearchArabicNamesUsingRegexp::convertNameToRegexp($this->name));
                                break;
                            case 'like':
                                $query->orWhere($columnName, 'like', "%{$this->name}%");
                                break;
                            case 'equals':
                                $query->orWhere($columnName, '=', $this->trimmedName);
                                break;
                            default:
                                $query->orWhere($columnMethod, 'like', "%{$this->name}%");
                        }
                    }
                });

            }
            $total = $this->modelQuery->count();
            $items = $this->modelQuery
                ->orderBy(array_key_first($this->filterColumns))
                ->offset($this->offset)
                ->limit($this->_limit)
                ->get();
        } else {
            $items = [];
            $total = 0;
        }

        return ApiResponse::success(InfiniteScrollResponseData::from([
            'items' => $items,
            'total' => $total,
        ]));
    }
}
