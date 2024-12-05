<?php

namespace HassanDomeDenea\HddLaravelHelpers;

use App\Http\Requests\StoreLaboratoryRequest;
use App\Models\Laboratory;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend\PrimeVueDataTableService;
use HassanDomeDenea\HddLaravelHelpers\Requests\DestroyManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\InfiniteScrollRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\StoreManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\UpdateManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Services\InfiniteScrollSearcherService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use OwenIt\Auditing\Contracts\Auditable;
use Throwable;

class BaseCrudController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public string $modelClass = BaseModel::class;
    public string|null $dataClass = null;
    public string|null $storeFormRequestClass = null;
    public string|null $updateFormRequestClass = null;

    public function index(PrimeVueDataTableService $dtService): JsonResponse
    {
        $dtService->setModel($this->modelClass);
        if ($this->dataClass) {
            $dtService->setDataClass($this->dataClass);
        }

        return ApiResponse::success($dtService->proceed());
    }

    public function show(BaseModel $modelInstance): JsonResponse
    {
        return ApiResponse::success($modelInstance);
    }

    public function store(): JsonResponse
    {
        if ($this->storeFormRequestClass) {
            /** @var FormRequest $request */
            $request = app($this->storeFormRequestClass);
            if (method_exists($request, 'authorize')) {
                abort_unless($request->authorize(), 403);
            }
            $attributes = $request->validated();
        } else {
            $attributes = request()->all();
        }
        $modelInstance = $this->modelClass::create($attributes);

        return ApiResponse::success($modelInstance, 201);
    }


    public function storeMany(StoreManyRequest $request): JsonResponse
    {
        $ids = $this->modelClass::checkAndCreateMany($this->storeFormRequestClass, $request);

        return ApiResponse::success($ids);
    }

    public function update(): JsonResponse
    {
        $modelInstanceName = Str::snake(class_basename($this->modelClass));
        $id = request()->route($modelInstanceName);
        $modelInstance = $this->modelClass::findOrFail($id);
        request()->route()->setParameter($modelInstanceName, $modelInstance);
        if ($this->updateFormRequestClass) {
            /** @var FormRequest $request */
            $request = app($this->updateFormRequestClass);
           
            if (method_exists($request, 'authorize')) {
                abort_unless($request->authorize(), 403);
            }
            $attributes = $request->validated();
        } else {
            $attributes = request()->all();
        }
        $modelInstance->update($attributes);

        return ApiResponse::success($modelInstance);
    }

    public function updateMany(UpdateManyRequest $request): JsonResponse
    {
        $ids = $this->modelClass::checkAndUpdateMany($this->updateFormRequestClass, $request);

        return ApiResponse::success($ids);
    }

    public function destroy($id): JsonResponse
    {
        $modelInstance = $this->modelClass::findOrFail($id);

        $modelInstance->checkAndDelete();

        return ApiResponse::success();
    }

    public function destroyMany(DestroyManyRequest $request): JsonResponse
    {

        $this->modelClass::checkAndDeleteMany($request->ids);

        return ApiResponse::success();
    }


    public function search(InfiniteScrollRequest $request): JsonResponse
    {
        $query = $this->modelClass::query()->orderBy('name');
        return new InfiniteScrollSearcherService($request, $query, ['name' => 'regexp'])->proceed();
    }

}
