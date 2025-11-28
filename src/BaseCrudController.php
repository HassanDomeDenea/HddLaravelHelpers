<?php
declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers;

use Gate;
use HassanDomeDenea\HddLaravelHelpers\Data\Requests\ListModelRequestData;
use HassanDomeDenea\HddLaravelHelpers\Data\Requests\ReorderRequestData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\Helpers\AuditableUtilities;
use HassanDomeDenea\HddLaravelHelpers\Helpers\CrudeHelpers;
use HassanDomeDenea\HddLaravelHelpers\Helpers\PathHelpers;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\Requests\DestroyManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\InfiniteScrollRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\StoreManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\UpdateManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Services\InfiniteScrollSearcherService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Spatie\LaravelData\Data;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * @template TModelClass of BaseModel
 */
class BaseCrudController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


    /**
     * @var class-string<TModelClass> | null
     */
    public string|null $modelClass = null;
    public string|null $policyClass = null;
    protected array $allowedIncludes = [];
    protected array $allowedFilters = [];

    public ?string $dataClass = null;

    public ?string $storeFormRequestClass = null;

    public ?string $updateFormRequestClass = null;
    public ?string $createActionClass = null;
    public ?string $updateActionClass = null;

    public ?string $pluckByColumnName = null;

    /**
     * @param class-string<TModelClass> $modelClass
     * @return self<TModelClass>
     */
    public static function for(string $modelClass): self
    {
        /** @var self<TModelClass> $instance */
        $instance = new self();
        $instance->modelClass = $modelClass;
        return $instance;
    }


    /**
     * @return class-string<TModelClass>
     */
    protected function getModalClass(): string
    {
        return $this->modelClass ?? 'App\Models\\' . str(static::class)->afterLast('\\')->before('Controller');
    }

    /**
     * @return QueryBuilder<TModelClass>
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        return tap(QueryBuilder::for($this->getModalClass()),
            function (QueryBuilder $queryBuilder) {
                if (filled($this->allowedIncludes)) {
                    $queryBuilder->allowedIncludes($this->allowedIncludes);
                }
                if (filled($this->allowedFilters)) {
                    $queryBuilder->allowedFilters($this->allowedFilters);
                }
            });
    }

    protected function getDefaultOrderColumn(): string|null
    {
        return $this->defaultOrderColumn ?? null;
    }


    /**
     * @return class-string<TModelClass>
     */
    protected function getPolicyClass(): string
    {
        return $this->policyClass ?? 'App\Policies\\' . str(static::class)->afterLast('\\')->before('Controller')->append('Policy');
    }

    /**
     * @return class-string<Data>| null
     */
    public function getDataClass(): string|null
    {
        if ($this->dataClass && class_exists($this->dataClass)) {
            return $this->dataClass;
        } else if ($dataClass = PathHelpers::getDataClassFromModelClass($this->getModalClass())) {
            return $dataClass;
        } else {
            return null;
        }
    }

    /**
     * @return class-string<Data>| null
     */
    public function getStoreDataClass(): string|null
    {
        if ($dataClass = PathHelpers::getDataClassFromModelClass($this->getModalClass(), 'Store')) {
            return $dataClass;
        } else {
            return $this->getDataClass();
        }
    }

    /**
     * @return class-string<FormRequest>| null
     */
    public function getStoreFormRequestClass(): string|null
    {
        return $this->storeFormRequestClass;
    }

    /**
     * @return class-string<Data>| null
     */
    public function getUpdateDataClass(): string|null
    {
        if ($dataClass = PathHelpers::getDataClassFromModelClass($this->getModalClass(), 'Update')) {
            return $dataClass;
        } else if ($dataClass = PathHelpers::getDataClassFromModelClass($this->getModalClass(), 'Store')) {
            return $dataClass;
        } else {
            return $this->getDataClass();
        }
    }

    /**
     * @return class-string<FormRequest>| null
     */
    public function getUpdateFormRequestClass(): string|null
    {
        return $this->updateFormRequestClass;
    }

    /**
     * @return class-string | null
     */
    public function getCreateActionClass(): string|null
    {
        if ($this->createActionClass && class_exists($this->createActionClass)) {
            return $this->createActionClass;
        } else if ($createActionClass = PathHelpers::getCreateActionClassFromModelClass($this->getModalClass())) {
            return $createActionClass;
        } else {
            return null;
        }
    }

    /**
     * @return class-string| null
     */
    public function getUpdateActionClass(): string|null
    {
        if ($this->updateActionClass && class_exists($this->updateActionClass)) {
            return $this->updateActionClass;
        } else if ($updateActionClass = PathHelpers::getUpdateActionClassFromModelClass($this->getModalClass())) {
            return $updateActionClass;
        } else {
            return null;
        }
    }

    public function list(): ApiResponse
    {
        if ($policyClass = $this->getPolicyClass()) {
            if(method_exists($policyClass, 'viewList')){
                Gate::authorize('viewList', $this->getModalClass());
            }else{
                Gate::authorize('viewAny', $this->getModalClass());
            }
        }

        $listModelRequestData = ListModelRequestData::validateAndCreate(request()->all());
        $builderQuery = $this->getQueryBuilder()
            ->when(filledOptional($listModelRequestData->filterBy), function ($query) use ($listModelRequestData) {
                $query->where($listModelRequestData->filterBy, $listModelRequestData->filterValue);
            })
            ->when(filledOptional($listModelRequestData->orderBy), function ($query) use ($listModelRequestData) {
                $query->orderBy($listModelRequestData->orderBy, $listModelRequestData->orderByDirection);
            });
        if (filledOptional($pluckColumn = $this->pluckByColumnName ?: $listModelRequestData->plucked)) {
            $result = $builderQuery->pluck($pluckColumn, $listModelRequestData->pluckBy);
        } else {
            $dataClass = $this->getDataClass();
            $result = $dataClass ? $this->getDataClass()::collect($builderQuery->get()) : $builderQuery->get();
        }

        return ApiResponse::successResponse($result);
    }

    public function index(DataTable $dt): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('viewAny', $this->getModalClass());
        }
         $dt->setModel($this->getModalClass());
        $dt->setModel($this->getQueryBuilder());
        $dt->setDataClass($this->getDataClass());

        return ApiResponse::successResponse($dt->proceed());
    }

    public function datatable(DataTable $dt): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('viewAny', $this->getModalClass());
        }
         $dt->setModel($this->getModalClass());
        $dt->setModel($this->getQueryBuilder());
        $dt->setDataClass($this->getDataClass());

        return ApiResponse::successResponse($dt->proceed());
    }

    public function show($modelId): JsonResponse
    {
        $modelInstance = $this->getQueryBuilder()->findOrFail($modelId);
        if ($this->getPolicyClass()) {
            Gate::authorize('view', $modelInstance);
        }
        $dataClass = $this->getDataClass();
        return ApiResponse::successResponse($dataClass ? $dataClass::from($modelInstance) : $modelInstance);
    }


    /**
     * @throws BindingResolutionException
     */
    public function store(): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('create', $this->getModalClass());
        }
        $actionClassName = $this->getCreateActionClass();
        $actionClassAttributeType = PathHelpers::getActionClassAttributeType($actionClassName);

        $attributes = CrudeHelpers::getAttributesFromAnything($this->getStoreFormRequestClass(), $this->getStoreDataClass(), request()->all(), $actionClassAttributeType);
        if ($actionClassName) {
            $modelInstance = app()->make($actionClassName)->handle($attributes);
        } else {
            $modelInstance = $this->getModalClass()::create($attributes);
        }
        $dataClass = $this->getDataClass();

        return ApiResponse::successResponse($dataClass ? $dataClass::from($modelInstance) : $modelInstance, 201);
    }

    public function storeMany(StoreManyRequest $request): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('create', $this->getModalClass());
        }
        $ids = [];

        try {
            DB::transaction(function () use ($request, &$ids) {
                $dataList = $request->array('data');

                $actionClassName = $this->getCreateActionClass();
                $actionClassAttributeType = PathHelpers::getActionClassAttributeType($actionClassName);
                $primaryKeyName = $this->getModalClass()::getTablePrimaryKey() ?: 'id';
                foreach ($dataList as $itemData) {
                    $attributes = CrudeHelpers::getAttributesFromAnything($this->getStoreFormRequestClass(), $this->getStoreDataClass(), $itemData, $actionClassAttributeType);
                    if ($actionClassName) {
                        $modelInstance = app()->make($actionClassName)->handle($attributes);
                    } else {
                        $modelInstance = $this->getModalClass()::create($attributes);
                    }

                    if ($modelInstance) {
                        $ids[] = $modelInstance[$primaryKeyName];
                    }
                }
            });
        } catch (Throwable $throwable) {
            return ApiResponse::failedResponse($throwable->getMessage());
        }
        return ApiResponse::successResponse($ids,201);
    }

    /**
     * @throws BindingResolutionException
     */
    public function update(): JsonResponse
    {
        $modelClass = $this->getModalClass();
        $modelInstanceName = Str::snake(class_basename($modelClass));
        $id = request()->route($modelInstanceName);
        $modelInstance = $modelClass::findOrFail($id);

        if ($this->getPolicyClass()) {
            Gate::authorize('update', $modelInstance);
        }
        request()->route()->setParameter($modelInstanceName, $modelInstance);

        $actionClassName = $this->getUpdateActionClass();
        $actionClassAttributeType = PathHelpers::getActionClassAttributeType($actionClassName, 1);
        $attributes = CrudeHelpers::getAttributesFromAnything($this->getUpdateFormRequestClass(), $this->getUpdateDataClass(), request()->all(), $actionClassAttributeType);
        if ($actionClassName) {
            $modelInstance = app()->make($actionClassName)->handle($modelInstance, $attributes);
        } else {
            $modelInstance->update($attributes);
        }
        $dataClass = $this->getDataClass();

        return ApiResponse::successResponse($dataClass ? $dataClass::from($modelInstance) : $modelInstance);
    }

    public function updateMany(UpdateManyRequest $request): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('updateMany', $request->array('data.*.ids'));
        }
        $ids = [];
        try {
            DB::transaction(function () use ($request, &$ids) {
                $dataList = $request->array('data');

                $actionClassName = $this->getUpdateActionClass();
                $actionClassAttributeType = PathHelpers::getActionClassAttributeType($actionClassName, 1);

                $modelBindingName = Str::snake(class_basename($this->getModalClass()));
                $primaryKeyName = $this->getModalClass()::getTablePrimaryKey() ?: 'id';
                $modelsList = $this->getModalClass()::query()->findMany(Arr::pluck($dataList, $primaryKeyName));
                foreach ($dataList as $itemId => $itemData) {
                    $id = $itemData[$primaryKeyName] ?? $itemId;
                    $modelInstance = $modelsList->where($primaryKeyName, $id)->firstOrFail();
                    $attributes = CrudeHelpers::getAttributesFromAnything($this->getUpdateFormRequestClass(), $this->getUpdateDataClass(), $itemData, $actionClassAttributeType, formRequestExtraBindings: [$modelBindingName => $modelInstance]);
                    if ($actionClassName) {
                        $modelInstance = app()->make($actionClassName)->handle($modelInstance, $attributes);
                    } else {
                        $modelInstance->update($attributes);
                    }

                    if ($modelInstance) {
                        $ids[] = $modelInstance[$primaryKeyName];
                    }
                }
            });
        } catch (Throwable $throwable) {
            return ApiResponse::failedResponse($throwable->getMessage());
        }

        return ApiResponse::successResponse($ids);
    }

    public function destroy(string|int $id): JsonResponse
    {

        $modelInstance = $this->getModalClass()::findOrFail($id);
        if ($this->getPolicyClass()) {
            Gate::authorize('delete', $modelInstance);
        }
        $modelInstance->checkAndDelete();

        return ApiResponse::successResponse();
    }

    public function destroyMany(DestroyManyRequest $request): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('deleteMany', $request->array('ids'));
        }
        $this->getModalClass()::checkAndDeleteMany($request->array('ids'));

        return ApiResponse::successResponse();
    }

    public function search(InfiniteScrollRequest $request): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('viewAny', $this->getModalClass());
        }
        $query = $this->getQueryBuilder();
        return (new InfiniteScrollSearcherService($request, $query, ['name' => 'regexp'], defaultOrderColumn: $this->getDefaultOrderColumn()))
            ->setDataClass($this->getDataClass())->proceed();
    }

    public function reorder(ReorderRequestData $requestData): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('reorder', $this->getModalClass());
        }
        $success = false;
        try {
            if (method_exists($this->getModalClass(), 'reorderSequence')) {
                $success = $this->getModalClass()::reorderSequence($requestData->from_order, $requestData->to_order, $requestData->scopedValues);
            }
        } catch (Throwable $e) {
            return ApiResponse::failedResponse($e->getMessage());
        }

        if (!$success) {
            return ApiResponse::failedResponse('Failed to reorder items. The source item was not found.', 404);
        }

        return ApiResponse::successResponse();
    }

    public function audits(string|int $id, Request $request): ApiResponse
    {
        $request->validate([
            'field' => ['required', 'string'],
            'with_all_values' => ['boolean'],
        ]);

        /** @var BaseModel $modelInstance */
        $modelInstance = $this->getModalClass()::findOrFail($id);

        return ApiResponse::successResponse(
            AuditableUtilities::FormatAuditQuery(
                $modelInstance->audits(),
                $request->input('field'),
                $request->boolean('with_all_values'),
            )
        );
    }
}
