<?php

namespace HassanDomeDenea\HddLaravelHelpers;

use Gate;
use HassanDomeDenea\HddLaravelHelpers\Data\Requests\ListModelRequestData;
use HassanDomeDenea\HddLaravelHelpers\Data\Requests\ReorderRequestData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\Helpers\PathHelpers;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\DataTable;
use HassanDomeDenea\HddLaravelHelpers\Requests\DestroyManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\InfiniteScrollRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\StoreManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\UpdateManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Services\InfiniteScrollSearcherService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;
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
        return QueryBuilder::for($this->getModalClass());
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
        if ($this->dataClass && class_exists($this->dataClass)) {
            return $this->dataClass;
        } else if ($dataClass = PathHelpers::getDataClassFromModelClass($this->getModalClass(), 'Store')) {
            return $dataClass;
        } else {
            return $this->getDataClass();
        }
    }

    /**
     * @return class-string<Data>| null
     */
    public function getUpdateDataClass(): string|null
    {
        if ($this->dataClass && class_exists($this->dataClass)) {
            return $this->dataClass;
        } else if ($dataClass = PathHelpers::getDataClassFromModelClass($this->getModalClass(), 'Update')) {
            return $dataClass;
        } else {
            return $this->getDataClass();
        }
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

    public function list()
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('viewAny', $this->getModalClass());
        }

        $listModelRequestData = ListModelRequestData::validateAndCreate(request()->all());
        $builderQuery = $this->getModalClass()::query()
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
        $dt->setDataClass($this->getDataClass());

        return ApiResponse::successResponse($dt->proceed());
    }

    public function show($modelId): JsonResponse
    {
        $modelInstance = $this->getModalClass()::findOrFail($modelId);
        if ($this->getPolicyClass()) {
            Gate::authorize('view', $modelInstance);
        }
        $dataClass = $this->getDataClass();
        return ApiResponse::successResponse($dataClass ? $dataClass::from($modelInstance) : $modelInstance);
    }

    /**
     * @throws \ReflectionException
     */
    public function store(): JsonResponse
    {
        if ($this->getPolicyClass()) {
            Gate::authorize('create', $this->getModalClass());
        }
        if ($this->storeFormRequestClass) {
            /** @var FormRequest $request */
            $request = app($this->storeFormRequestClass);
            if (method_exists($request, 'authorize')) {
                abort_unless($request->authorize(), 403);
            }
            $attributes = $request->validated();
        } elseif ($dataClass = $this->getStoreDataClass()) {
            if (method_exists($dataClass, 'authorize')) {
                $validationContext = new ValidationContext(
                    $payload = request()->all(),
                    $payload,
                    new ValidationPath()
                );
                abort_unless((bool) app()->call([$dataClass,'authorize'], ['context' => $validationContext]),403);
            }
            $attributes = $dataClass::validate(request()->all());
        } else {
            $attributes = request()->all();
        }
        if ($createActionClass = $this->getCreateActionClass()) {
            //TODO: Cache Reflection Result
            $expectedAttributesType = (new ReflectionClass($createActionClass))->getMethod('handle')->getParameters()[0]->getType()->getName() ?? null;
            if (!empty($dataClass) && ($expectedAttributesType) === $dataClass) {
                $attributes = $dataClass::from($attributes);
            } else if ($expectedAttributesType === Fluent::class) {
                $attributes = new Fluent($attributes);
            }
            $modelInstance = app($createActionClass)->handle($attributes);
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
        $ids = $this->getModalClass()::checkAndCreateMany($this->storeFormRequestClass ?: $this->getStoreDataClass(), $request);

        return ApiResponse::successResponse($ids);
    }

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
        if ($this->updateFormRequestClass) {
            /** @var FormRequest $request */
            $request = app($this->updateFormRequestClass);

            if (method_exists($request, 'authorize')) {
                abort_unless($request->authorize(), 403);
            }
            $attributes = $request->validated();
        } elseif ($dataClass = $this->getUpdateDataClass()) {
            if (method_exists($dataClass, 'authorize')) {
                $validationContext = new ValidationContext(
                    $payload = request()->all(),
                    $payload,
                    new ValidationPath()
                );
                app()->call([$dataClass, 'authorize'], ['context' => $validationContext, $modelInstanceName => $modelInstance]);
            }
            $attributes = $dataClass::validate(request()->all());
        } else {
            $attributes = request()->all();
        }
        if ($updateActionClass = $this->getUpdateActionClass()) {

            //TODO: Cache Reflection Result
            $expectedAttributesType = (new ReflectionClass($updateActionClass))->getMethod('handle')->getParameters()[1]->getType()->getName() ?? null;
            if (!empty($dataClass) && ($expectedAttributesType) === $dataClass) {
                $attributes = $dataClass::from($attributes);
            } else if ($expectedAttributesType === Fluent::class) {
                $attributes = new Fluent($attributes);
            }

            app($updateActionClass)->handle($modelInstance, $attributes);
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
        $ids = $this->getModalClass()::checkAndUpdateMany($this->updateFormRequestClass ?: $this->getUpdateDataClass(), $request);

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

        try {
            $success = $this->getModalClass()::reorderSequence($requestData->from_order, $requestData->to_order, $requestData->scopedValues);
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            $success = false;
        }

        if (!$success) {
            return ApiResponse::failedResponse('Failed to reorder items. The source item was not found.', 404);
        }

        return ApiResponse::successResponse();
    }
}
