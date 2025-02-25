<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use HassanDomeDenea\HddLaravelHelpers\Attributes\RequestBodyAttribute;
use HassanDomeDenea\HddLaravelHelpers\Attributes\ResponseAttribute;
use HassanDomeDenea\HddLaravelHelpers\Helpers\AppendableString;
use HassanDomeDenea\HddLaravelHelpers\Helpers\MutableStringable;
use HassanDomeDenea\HddLaravelHelpers\Helpers\PathHelpers;
use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use stdClass;
use Symfony\Component\Filesystem\Path;
use function Orchestra\Testbench\transform_relative_path;

class GenerateRoutesTypesCommand extends Command
{
    protected $signature = 'hdd:routes-ts {--url=}';

    protected $description = 'Convert Routes To Typescript file';

    private string|null $typescriptTransformRelativePath = null;

    private array $typescriptTypesToImport = [];
    private array $typescriptTypesToImportIgnoreList = [];

    private string $routesDefinitionConstName = "laravelRoutes";
    private string $routesDefinitionByUrlConstName = "laravelRoutesByUrl";

    private string $apiAxiosInstanceName;

    private string $apiAxiosInstanceImportPath;

    private DocBlockFactoryInterface $docBlockFactory;
    private array $phpToTsTypeMap = [
        'int' => 'number',
        'float' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'array' => 'any[]',
        'object' => 'object',
        'mixed' => 'any',
        'null' => 'null',
        'void' => 'void'
    ];

    public function __construct(private readonly Filesystem $files)
    {
        $this->docBlockFactory = DocBlockFactory::createInstance();
        parent::__construct();
    }

    private function tabSpace(int $number, int $spaces = 2): string
    {
        $spacesPerTab = str_repeat(' ', $spaces);

        return str_repeat($spacesPerTab, $number);
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $path = base_path($this->option('url') ?: config()->string('hdd-laravel-helpers.routes_types_ts_file_path'));

        $typescriptTransformFile = config('typescript-transformer.output_file');
        $typescriptTransformIsModular = str(config('typescript-transformer.writer'))->endsWith('ModuleWriter');

        if (!empty($typescriptTransformFile) && $typescriptTransformIsModular) {
            $this->typescriptTransformRelativePath = './' . Path::makeRelative($typescriptTransformFile, dirname($path));

        }
        $this->apiAxiosInstanceImportPath = config()->string('hdd-laravel-helpers.axios-instance-import-path', "./axios");
        $this->apiAxiosInstanceName = config()->string('hdd-laravel-helpers.axios-instance-import-name', "apiAxiosInstance");
        $this->typescriptTypesToImportIgnoreList = config()->array('hdd-laravel-helpers.routes-ts-ignore-responses-types', ['Response', 'JsonResponse']);

        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }
        $output = $this->getRoutesTypesOutput();
        $this->files->put($path, $output);

        if (config()->boolean('hdd-laravel-helpers.rotes_types_ts_eslint', false) && strlen(shell_exec('bun -v')) < 20) {
            $this->info('Fixing');
            $this->info(shell_exec("bun eslint \"$path\" --fix"));
        }
        //        $this->info($this->getRoutesTypesOutput());
        $this->info('Done');
        //        shell_exec("bun eslint \"$path\" --fix");
    }

    private function getRoutesTypesOutput(): string
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
        $routes = Route::getRoutes()->getRoutes();
        $result = collect();
        foreach ($routes as $route) {
            if (!Str::startsWith($route->uri(), 'api') && !Str::startsWith($route->uri(), 'sanctum')) {
                continue;
            }
            $uri = $route->uri();
            $routeRules = [];
            $routeResponseType = null;

            if (Str::startsWith($uri, ['api', '/api'])) {
                $uri = Str::after($uri, 'api/');
            } else {
                $uri = Str::start($uri, '/');
            }
            foreach ($route->methods() as $method) {
                if (!in_array($method, $allowedMethods)) {
                    continue;
                }


                //Checking for Request Rules
                $formRequests = $route->signatureParameters(['subClass' => FormRequest::class]);

                if (count($formRequests) > 0) {
                    /** @var \ReflectionParameter $parameter */
                    $parameter = $formRequests[0];
                    $parameterType = $parameter->getType();
                    if (!$parameterType || !method_exists($parameterType, 'getName')) {
                        continue;
                    }
                    $parameterClassName = $parameterType->getName();

                    if (class_exists($parameterClassName)) {

                        /** @var ReflectionClass<FormRequest> $reflectionClass */
                        $reflectionClass = new ReflectionClass($parameterClassName);

                        if ($reflectionClass->isInstantiable() && $reflectionClass->hasMethod('rules')) {
                            try {
                                $formRequestClassInstance = $reflectionClass->newInstance();
                                if (method_exists($formRequestClassInstance, 'rules')) {
                                    $routeRules = $formRequestClassInstance->rules();
                                }
                            } catch (ReflectionException) {

                            }
                        }
                    }
                }

                $controllerClassName = $route->getControllerClass();
                if ($controllerClassName) {
                    $controllerMethodName = $route->getActionMethod();

                    if ($controllerMethodName) {

                        if ($controllerMethodName === $controllerClassName) {
                            $controllerMethodName = '__invoke';
                        }

                        if (class_exists($controllerClassName) && method_exists($controllerClassName, $controllerMethodName)) {
                            $methodReflection = new ReflectionMethod($controllerClassName, $controllerMethodName);

                            if ($attributes = $methodReflection->getAttributes(RequestBodyAttribute::class)) {
                                foreach ($attributes as $attribute) {
                                    $routeRules += $attribute->newInstance()->getRules();
                                }
                            }

                            if ($attributes = $methodReflection->getAttributes(ResponseAttribute::class)) {

                                $routeResponseType = $attributes[0]->newInstance()->classNameToTypeScript();
                                if (!empty($routeResponseType) && !in_array($routeResponseType, $this->typescriptTypesToImport)) {
                                    $this->typescriptTypesToImport[] = $routeResponseType;
                                }
                            } else {
                                $routeResponseType = $this->parseMethodReturnTypeFromDocComment($methodReflection);
                            }

                        }

                    }
                }


                // End Checking for Request Rules

                //Getting Route Parameters
                /** @var array<string,'required'|'optional'> $routeParameters */
                $routeParameters = [];
                foreach ($route->parameterNames() as $name) {
                    if (Str::contains($route->uri(), '{' . $name . '?}')) {
                        $routeParameters[] = ['name' => $name, 'isRequired' => false];
                    } else {
                        $routeParameters[] = ['name' => $name, 'isRequired' => true];
                    }
                }

                //End Route Parameters

                //Start Getting Route Bindings

                /** @var array<string,string|null> $routeBindableParameters */
                $routeBindableParameters = [];
                $parameters = $route->signatureParameters([UrlRoutable::class]);
                foreach ($parameters as $parameter) {
                    $parameterType = $parameter->getType();
                    if (!$parameterType || !method_exists($parameterType, 'getName')) {
                        continue;
                    }
                    $parameterClassName = $parameterType->getName();

                    if (!$parameterClassName || !class_exists($parameterClassName)) {
                        continue;
                    }
                    /** @var ReflectionClass<Model> $reflectionClass */
                    $reflectionClass = new ReflectionClass($parameterClassName);

                    $bindingValue = $reflectionClass->hasMethod('getRouteKeyName') ? app($parameterClassName)->getRouteKeyName() : null;
                    if ($bindingValue) {
                        $routeBindableParameters[$parameter->name] = $bindingValue;
                        $k = array_find_key($routeParameters, fn($i) => $i['name'] === $parameter->name);
                        if ($k > -1) {
                            $routeParameters[$k]['binding'] = $bindingValue;
                        }
                    }
                }
                //End Getting Route Bindings


                $result->add([
                    'name' => $route->getName(),
                    'uri' => $uri,
                    'method' => $method,
                    'rules' => $routeRules ?: new stdClass(),
                    'parameters' => $routeParameters,
                    'bindings' => $routeBindableParameters ?: new stdClass(),
                    'response' => $routeResponseType,
                ]);

            }
        }

        $routesTypescriptDefinition = $this->generaRoutesTypescriptDefinition($result);

        $routesKeyedByName = $result->unique('name')->keyBy('name')->toJson(JSON_PRETTY_PRINT);

        $importTsDataTypes = '';

        $methodsDeclarations = $this->getMethodsDeclarations($allowedMethods);
        if ($this->typescriptTransformRelativePath) {
            $dataNames = join(", ", collect($this->typescriptTypesToImport)->unique()->filter(function ($i) {
                return !in_array($i, $this->typescriptTypesToImportIgnoreList);
            })->toArray());
            $importTsDataTypes = "import type { {$dataNames} } from '{$this->typescriptTransformRelativePath}';";
        }

        return <<<TS
/* This file is auto generated */
import type { AxiosRequestConfig, AxiosResponse } from 'axios'
$importTsDataTypes
import { {$this->apiAxiosInstanceName} } from '{$this->apiAxiosInstanceImportPath}'

//Helpers:

function isAxiosRequestConfig(config: unknown): config is AxiosRequestConfig {
  if (typeof config !== 'object' || config === null) {
    return false;
  }
  return 'params' in config || 'baseURL' in config;
}

function generateRouteUrl(route: RouteItemType, param?: ParameterType | ParameterType[] | { [key: string]: ParameterType }): string {
  let url = route.uri
  if (route.parameters.length > 0 && param) {
    if (typeof param === 'object') {
      if (Array.isArray(param)) {
        for (let i = 0; i < route.parameters.length; i++) {
          if (param[i] !== undefined)
            url = replaceParamWithValue(url, route.parameters[i].name, param[i])
        }
      }
      else {
        for (const i in param) {
          const routeParameter = route.parameters.find(e => e.name === i || e.binding === i)
          if (routeParameter) {
            url = replaceParamWithValue(url, routeParameter.name, param[i])
          }
        }
      }
    }
    else {
      url = replaceParamWithValue(url, route.parameters[0].name, param)
    }
  }
  return url
}

function replaceParamWithValue(url: string, param: string, value: any): string {
  url = url.replace(`{\${param}}`, value)
  url = url.replace(`{\${param}?}`, value)
  return url
}

function processPostPutRoutesArgs(routeItem: RouteItemType, args: any[]) {
  let config, body, routeParameters
  if (args.length > 0) {
        if (routeItem.parameters.length > 0) {
            routeParameters = args[0]
            if (Object.keys(routeItem.rules).length > 0) {
                body = args[1]
                config = args[2]
            } else {
                config = args[1]
            }
        } else if (Object.keys(routeItem.rules).length > 0) {
            body = args[0]
            config = args[1]
        } else {
            config = args[0]
        }
    }
  return {
    config,
    body,
    routeParameters,
  }
}

function processGetDeleteRoutesArgs(routeItem: RouteItemType, args: any[]) {
  let url
  if (args[1] || (args[0] && !isAxiosRequestConfig(args[0])))
    url = generateRouteUrl(routeItem, args[0])
  else
    url = generateRouteUrl(routeItem)

  const config = args[1] || args[0]

  return {
    url,
    config,
  }
}

type RouteMethodType = 'GET' | 'POST' | 'PUT' | 'DELETE'
type RouteNameType = string | null
type RoutePathType = string
type ParameterType = number | string
type RouteItemType = {
  name: RouteNameType
  uri: RoutePathType
  method: RouteMethodType
  rules: Record<string, any>
  parameters: { name: string, isRequired: boolean, binding?: string } []
  bindings: Record<string, string>
  response: any
}

$routesTypescriptDefinition

const laravelRoutes: {[key: string]: RouteItemType} = $routesKeyedByName

$methodsDeclarations

export const api: {
  getByName: typeof axiosGetByName
  deleteByName: typeof axiosDeleteByName
  postByName: typeof axiosPostByName
  putByName: typeof axiosPutByName
  get: typeof axiosGet
  post: typeof axiosPost
  put: typeof axiosPut
  delete: typeof axiosDelete
} = {
  getByName: axiosGetByName,
  deleteByName: axiosDeleteByName,
  postByName: axiosPostByName,
  putByName: axiosPutByName,
  get: axiosGet,
  post: axiosPost,
  put: axiosPut,
  delete: axiosDelete,
}
TS;

    }

    private function getRouteItemByNameResponseType(array $routes): string
    {
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($routes['types'] as $paths) {
            foreach ($paths as $pathData) {
                if (!$pathData['name']) {
                    continue;
                }

                $typesResponsesStr .= $this->tabSpace(1) . "'" . $pathData['name'] . "': " . ($pathData['response'] ?: 'null') . PHP_EOL;
            }
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;
    }

    private function getRouteItemByUrlResponseType(array $routes): string
    {
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($routes['types'] as $method => $paths) {
            $typesResponsesStr .= $this->tabSpace(1) . $method . ': {' . PHP_EOL;
            foreach ($paths as $pathData) {
                $typesResponsesStr .= $this->tabSpace(2) . "'" . $pathData['url'] . "': " . ($pathData['response'] ?: 'null') . PHP_EOL;
            }
            $typesResponsesStr .= $this->tabSpace(1) . '}' . PHP_EOL;
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;
    }

    private function getLaravelRoutesConst($routes): string
    {
        $typesStr = '{' . PHP_EOL;
        foreach ($routes['types'] as $method => $paths) {
            $typesStr .= $this->tabSpace(1) . $method . ': {' . PHP_EOL;
            foreach ($paths as $pathName => $pathData) {
                $typesStr .= $this->tabSpace(2) . "'" . $pathName . "': {" . PHP_EOL;
                $typesStr .= $this->tabSpace(3) . "url: '" . $pathData['url'] . "'" . PHP_EOL;
                $typesStr .= $this->tabSpace(3) . "name: '" . $pathData['name'] . "'" . PHP_EOL;
                $typesStr .= $this->tabSpace(3) . 'response: ' . ($pathData['response'] ?: 'null') . PHP_EOL;
                if (!empty($pathData['parameters'])) {

                    // Object Type
                    $typesStr .= $this->tabSpace(3) . 'parameters: {' . PHP_EOL;

                    foreach ($pathData['parameters'] as $parameterName => $parameterType) {
                        $typesStr .= $this->tabSpace(4) . $parameterName . ': ' . $parameterType . PHP_EOL;
                    }
                    $typesStr .= $this->tabSpace(3) . '}' . PHP_EOL;

                    // Array Type
                    $optionals = Arr::where(array_keys($pathData['parameters']), fn($i) => Str::endsWith($i, '?'));
                    $typesStr .= $this->tabSpace(3) . 'parameters_array: [';
                    $typesStr .= Arr::join($pathData['parameters'], ', ');
                    foreach ($optionals as $n => $optional) {
                        $typesStr .= '] | [' . Arr::join(Arr::take($pathData['parameters'], count($pathData['parameters']) - $n), ', ');
                    }
                    $typesStr .= ']' . PHP_EOL;

                    // Single Type
                    if (count($pathData['parameters']) - count($optionals) === 1) {
                        $typesStr .= $this->tabSpace(3) . 'parameters_single: ' . $pathData['parameters'][array_key_first($pathData['parameters'])] . PHP_EOL;
                    } else {
                        $typesStr .= $this->tabSpace(3) . 'parameters_single: never' . PHP_EOL;
                    }
                } else {
                    $typesStr .= $this->tabSpace(3) . 'parameters: never' . PHP_EOL;
                    $typesStr .= $this->tabSpace(3) . 'parameters_array: []' . PHP_EOL;
                    $typesStr .= $this->tabSpace(3) . 'parameters_single: never' . PHP_EOL;
                }
                if (!empty($pathData['bindings'])) {

                    $typesStr .= $this->tabSpace(3) . 'bindings: {' . PHP_EOL;

                    foreach ($pathData['bindings'] as $bindingName => $bindingKey) {
                        $typesStr .= $this->tabSpace(4) . $bindingName . ': ' . "'$bindingKey'" . PHP_EOL;
                    }
                    $typesStr .= $this->tabSpace(3) . '}' . PHP_EOL;
                } else {
                    $typesStr .= $this->tabSpace(3) . 'bindings: []' . PHP_EOL;
                }
                $typesStr .= $this->getBodyRules($routes['rulesList'] ?? [], $this->tabSpace(3));

                $typesStr .= $this->tabSpace(2) . '}' . PHP_EOL;
            }
            $typesStr .= $this->tabSpace(1) . '}' . PHP_EOL;
        }
        $typesStr .= '}';

        return $typesStr;
    }

    private function getMethodsDeclarations(array $allowedMethods): string
    {
        $methodsDeclarations = <<<'TS'


TS;
        foreach ($allowedMethods as $method) {
            $smallLetterMethod = strtolower($method);
            if (in_array($method, ['GET', 'DELETE'])) {
                $ucFirstMethodName = ucfirst($smallLetterMethod);
                $methodsDeclarations .= <<<TS

// No Parameters No Payload | No Parameters Optional Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['{$method}']['noParametersAndNoPayload'] | routeRequirementsByNames['{$method}']['noParametersAndOptionalPayload']>(
  name: TName,
  config?: routesTypesByNames['{$method}'][TName]['payloadAsAxiosConfig']): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>>

// No Parameters Required Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['{$method}']['noParametersAndRequiredPayload']>(
  name: TName,
  config: routesTypesByNames['{$method}'][TName]['payloadAsAxiosConfig']): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>>

// Optional Parameters No Payload | Optional Parameters Optional Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['{$method}']['optionalParametersAndNoPayload'] | routeRequirementsByNames['{$method}']['optionalParametersAndOptionalPayload']>(
  name: TName,
  routeParameter?: routesTypesByNames['{$method}'][TName]['routeParameters'],
  config?: routesTypesByNames['{$method}'][TName]['payloadAsAxiosConfig']): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>>

// Optional Parameters Required Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['{$method}']['optionalParametersAndRequiredPayload']>(
  name: TName,
  routeParameter: routesTypesByNames['{$method}'][TName]['routeParameters'] | null,
  config: routesTypesByNames['{$method}'][TName]['payloadAsAxiosConfig']): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>>

// Required Parameters No Payload | Required Parameters Optional Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['{$method}']['requiredParametersAndNoPayload'] | routeRequirementsByNames['{$method}']['requiredParametersAndOptionalPayload']>(
  name: TName,
  routeParameter: routesTypesByNames['{$method}'][TName]['routeParameters'],
  config?: routesTypesByNames['{$method}'][TName]['payloadAsAxiosConfig']): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>>

// Required Parameters Required Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['{$method}']['requiredParametersAndRequiredPayload']>(
  name: TName,
  routeParameter: routesTypesByNames['{$method}'][TName]['routeParameters'],
  config: routesTypesByNames['{$method}'][TName]['payloadAsAxiosConfig']): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>>


export function axios{$ucFirstMethodName}ByName<TName extends keyof routesTypesByNames['{$method}']>(
  name: TName,
  ...args: any[]

): Promise<AxiosResponse<routesTypesByNames['{$method}'][TName]['response']>> {
    let routeItem = {$this->routesDefinitionConstName}[name];
    if (!routeItem) {
        return Promise.reject(new Error("Route was not found"));
    }
    const { url, config } = processGetDeleteRoutesArgs(routeItem, args)
    return {$this->apiAxiosInstanceName}.{$smallLetterMethod}(url, config)
}

export function axios{$ucFirstMethodName}<TResponse = any, TUri extends routeRequirementsByUris['$method']['noPayload'] | routeRequirementsByUris['$method']['optionalPayload'] | routeRequirementsByUris['$method']['requiredPayload'] = routeRequirementsByUris['$method']['noPayload'] | routeRequirementsByUris['$method']['optionalPayload'] | routeRequirementsByUris['$method']['requiredPayload']>(
    url: TUri,
    config?: AxiosRequestConfig): Promise<AxiosResponse<TResponse>> {
    return {$this->apiAxiosInstanceName}.{$smallLetterMethod}(url, config)
}

TS;

            }
            if (in_array($method, ['POST', 'PUT'])) {
                $ucFirstMethodName = ucfirst($smallLetterMethod);
                $methodsDeclarations .= <<<TS


// No Parameters No Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['noParametersAndNoPayload']>(
  name: TName,
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// No Parameters Optional Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['noParametersAndOptionalPayload']>(
  name: TName,
  body?: routesTypesByNames['$method'][TName]['payload'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// No Parameters Required Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['noParametersAndRequiredPayload']>(
  name: TName,
  body: routesTypesByNames['$method'][TName]['payload'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// Optional Parameters No Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['optionalParametersAndNoPayload']>(
  name: TName,
  routeParameters?: routesTypesByNames['$method'][TName]['routeParameters'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// Optional Parameters Optional Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['optionalParametersAndOptionalPayload']>(
  name: TName,
  routeParameters?: routesTypesByNames['$method'][TName]['routeParameters'],
  body?: routesTypesByNames['$method'][TName]['payload'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// Optional Parameters Required Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['optionalParametersAndRequiredPayload']>(
  name: TName,
  routeParameters: routesTypesByNames['$method'][TName]['routeParameters'] | null,
  body: routesTypesByNames['$method'][TName]['payload'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// Required Parameters No Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['requiredParametersAndNoPayload']>(
  name: TName,
  routeParameters: routesTypesByNames['$method'][TName]['routeParameters'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// Required Parameters Optional Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['requiredParametersAndOptionalPayload']>(
  name: TName,
  routeParameters: routesTypesByNames['$method'][TName]['routeParameters'],
  body?: routesTypesByNames['$method'][TName]['payload'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>

// Required Parameters Required Payload
export function axios{$ucFirstMethodName}ByName<TName extends routeRequirementsByNames['$method']['requiredParametersAndRequiredPayload']>(
  name: TName,
  routeParameters: routesTypesByNames['$method'][TName]['routeParameters'] | null,
  body: routesTypesByNames['$method'][TName]['payload'],
  config?: AxiosRequestConfig,
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>>


export function axios{$ucFirstMethodName}ByName<TName extends keyof routesTypesByNames['$method']>(
  name: TName,
  ...args: any[]
): Promise<AxiosResponse<routesTypesByNames['$method'][TName]['response']>> {
    let routeItem = {$this->routesDefinitionConstName}[name];
    if (!routeItem) {
        return Promise.reject(new Error("Route was not found"));
    }
    const { config, body, routeParameters } = processPostPutRoutesArgs(routeItem, args)

    let url = generateRouteUrl(routeItem, routeParameters);
    return {$this->apiAxiosInstanceName}.{$smallLetterMethod}(url, body, config)
}

export function axios{$ucFirstMethodName}<TResponse = any, TUri extends routeRequirementsByUris['$method']['noPayload'] = routeRequirementsByUris['$method']['noPayload']>(
  url: TUri,
  body?: null | [] | never,
  config?: AxiosRequestConfig): Promise<AxiosResponse<TResponse>>

export function axios{$ucFirstMethodName}<TResponse = any, TUri extends routeRequirementsByUris['$method']['optionalPayload'] = routeRequirementsByUris['$method']['optionalPayload']>(
  url: TUri,
  body?: any,
  config?: AxiosRequestConfig): Promise<AxiosResponse<TResponse>>

export function axios{$ucFirstMethodName}<TResponse = any, TUri extends routeRequirementsByUris['$method']['requiredPayload'] = routeRequirementsByUris['$method']['requiredPayload']>(
  url: TUri,
  body: any,
  config?: AxiosRequestConfig): Promise<AxiosResponse<TResponse>>


export function axios{$ucFirstMethodName}<TResponse = any, TUri extends routeRequirementsByUris['$method']['noPayload'] | routeRequirementsByUris['$method']['optionalPayload'] | routeRequirementsByUris['$method']['requiredPayload'] = routeRequirementsByUris['$method']['noPayload'] | routeRequirementsByUris['$method']['optionalPayload'] | routeRequirementsByUris['$method']['requiredPayload']>(
  url: TUri,
  body?: any,
  config?: AxiosRequestConfig): Promise<AxiosResponse<TResponse>>{
   return {$this->apiAxiosInstanceName}.{$smallLetterMethod}(url, body, config)
  }

TS;
            }

        }

        $methodsDeclarations .= config('hdd-laravel-helpers.routes_types_methods_declarations', false) === false ? '' : <<<'TS'

// Name Without Route Params & Without Body
export function axiosRequestByName<T extends keyof LaravelRoutesTypes['GET'], K extends LaravelRoutesTypes['GET'][T]['parameters']>(
  name: LaravelRoutesTypes['GET'][T]['parameters'] extends never ? (RouteItemByNameBodyType[T] extends undefined ? T : never) : never,
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[T]>>>

// Name Without Route Params & With Body
export function axiosRequestByName<T extends keyof LaravelRoutesTypes['GET'], K extends LaravelRoutesTypes['GET'][T]['parameters']>(
  name: K extends never ? (RouteItemByNameBodyType[T] extends undefined ? never : T) : never,
  config: {
    params: RouteItemByNameBodyType[T]
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[T]>>>

// Name With Route Params & Without Body
export function axiosRequestByName<T extends keyof LaravelRoutesTypes['GET'], K extends LaravelRoutesTypes['GET'][T]['parameters'], B extends LaravelRoutesTypes['GET'][T]['bindings']>(
  name: K extends never ? never : (RouteItemByNameBodyType[T] extends undefined ? T : never),
  routeParameters: Array<K[keyof K]>[number] | K | ConvertBindingsToObjects<B, K>,
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[T]>>>

// Name With Route Params & With Body
export function axiosRequestByName<T extends keyof LaravelRoutesTypes['GET'], K extends LaravelRoutesTypes['GET'][T]['parameters'], B extends LaravelRoutesTypes['GET'][T]['bindings']>(
  name: K extends never ? never : (RouteItemByNameBodyType[T] extends undefined ? never : T),
  routeParameters: Array<K[keyof K]>[number] | K | ConvertBindingsToObjects<B, K>,
  config: {
    params: RouteItemByNameBodyType[T]
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[T]>>>

export function axiosRequestByName<T extends keyof LaravelRoutesTypes['GET'], K extends LaravelRoutesTypes['GET'][T]['parameters'], B extends LaravelRoutesTypes['GET'][T]['bindings']>(
  name: T,
  routeParameters?: any,
  config?: any,
): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[T]>>> {
  let url
  if (config === undefined && routeParameters === undefined) {
    url = generateRouteUrl(laravelRoutes.GET[name], undefined)
    return apiAxiosInstance.get(url, config)
  }
  else if (isAxiosConfig(routeParameters)) {
    url = generateRouteUrl(laravelRoutes.GET[name], undefined)
    return apiAxiosInstance.get(url, config)
  }
  else {
    url = generateRouteUrl(laravelRoutes.GET[name], routeParameters)
    return apiAxiosInstance.get(url as string, config)
  }
}
TS;

        return $methodsDeclarations;
    }

    private function getBodyRules(array $rulesList, string $prefix = '', $rootPrefix = 'body: '): string
    {
        if ($rootPrefix) {
            $rootPrefix = $prefix . $rootPrefix;
        }
        if (empty($rulesList)) {
            return $rootPrefix . 'never' . (!empty($rootPrefix) ? PHP_EOL : '');
        }
        $bodyRules = $rootPrefix;
        $result = Arr::map($rulesList, function ($rules) {
            if (is_string($rules)) {
                return $rules;
            } elseif (is_array($rules)) {
                if (empty($rules)) {
                    return 'any';
                }
                $result = '{ ';
                foreach ($rules as $parameterName => $parameterRules) {
                    if (Str::contains($parameterName, '.')) {
                        continue;
                    }
                    $typesList = is_array($parameterRules) ? $parameterRules : explode('|', $parameterRules);
                    $optional = true;
                    $nullable = Arr::hasAny($typesList, ['nullable']);
                    $typeResultList = [];
                    if ($nullable) {
                        $typeResultList[] = 'null';
                    }
                    foreach ($typesList as $type) {
                        if ($type === 'required') {
                            $optional = false;
                        } elseif (is_object($type)) {
                            if (get_class($type) === RequiredIf::class) {
                                $optional = false;
                            }
                        }
                        $x = match ($type) {
                            'boolean' => 'boolean',
                            'string' => 'string',
                            'array' => 'any[]',
                            'object' => '{ [p in string]: any }',
                            'numeric', 'integer' => 'number',
                            default => '',
                        };
                        if (!empty($x)) {
                            $typeResultList[] = $x;
                        }
                    }
                    if (empty($typeResultList)) {
                        $typeResultList[] = 'any';
                    }
                    $typeResultList = array_unique($typeResultList);

                    $result .= $parameterName . ($optional ? '?' : '') . ': ' . implode('|', $typeResultList) . ', ';
                }

                return $result . ' }';

            } else {
                return null;
            }
        });
        $bodyRules .= Arr::join($result, ' & ');
        $bodyRules .= (!empty($rootPrefix) ? PHP_EOL : '');

        return $bodyRules;
    }

    private function hasRequiredRule(array|string $rules): bool
    {
        if (is_string($rules)) {
            return true;
        }
        foreach ($rules as $parameterName => $parameterRules) {
            $typesList = is_array($parameterRules) ? $parameterRules : explode('|', $parameterRules);
            foreach ($typesList as $type) {
                if ($type === 'required') {
                    return true;
                } elseif (is_object($type)) {
                    if (get_class($type) === RequiredIf::class) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getRouteItemByNameBodyType(array $routes): string
    {
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($routes['types'] as $paths) {
            foreach ($paths as $pathData) {
                if (!$pathData['name']) {
                    continue;
                }
                $typesResponsesStr .= $this->tabSpace(1) . "'" . $pathData['name'] . "': " . $this->getBodyRules($pathData['rulesList'] ?: [], $this->tabSpace(1), '') . PHP_EOL;
            }
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;
    }

    private function getRouteItemByUrlBodyType(array $routes): string
    {
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($routes['types'] as $method => $paths) {
            $typesResponsesStr .= $this->tabSpace(1) . $method . ': {' . PHP_EOL;
            foreach ($paths as $pathData) {
                $typesResponsesStr .= $this->tabSpace(2) . "'" . $pathData['url'] . "': " . $this->getBodyRules($pathData['rulesList'] ?: [], $this->tabSpace(2), '') . PHP_EOL;
            }
            $typesResponsesStr .= $this->tabSpace(1) . '}' . PHP_EOL;
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;
    }

    private function generateRouteNameByMethodsListFromArray(mixed $methodsRoutesList): string
    {
        if (empty($methodsRoutesList)) {
            return 'never' . PHP_EOL;
        }
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($methodsRoutesList as $method => $names) {
            $typesResponsesStr .= $this->tabSpace(1) . $method . ': '
                . (Arr::join(Arr::map($names, fn($i) => "'" . $i . "'"), ' | ') ?: 'never')
                . PHP_EOL;
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;
    }

    private function generaRoutesTypescriptDefinition(Collection $routes): string
    {

        $str = new AppendableString();
        $byUriStr = new AppendableString();
        $interfaceName = "routesTypesByNames";
        $interfaceByUriName = "routesTypesByUris";
        $str->append("interface $interfaceName {")->append(PHP_EOL);
        $byUriStr->append("interface $interfaceByUriName {")->append(PHP_EOL);

        $routeNames = [
            'GET' => [
                'noParametersAndNoPayload' => [],
                'noParametersAndOptionalPayload' => [],
                'noParametersAndRequiredPayload' => [],
                'optionalParametersAndNoPayload' => [],
                'optionalParametersAndOptionalPayload' => [],
                'optionalParametersAndRequiredPayload' => [],
                'requiredParametersAndNoPayload' => [],
                'requiredParametersAndOptionalPayload' => [],
                'requiredParametersAndRequiredPayload' => [],
            ],
            'POST' => [
                'noParametersAndNoPayload' => [],
                'noParametersAndOptionalPayload' => [],
                'noParametersAndRequiredPayload' => [],
                'optionalParametersAndNoPayload' => [],
                'optionalParametersAndOptionalPayload' => [],
                'optionalParametersAndRequiredPayload' => [],
                'requiredParametersAndNoPayload' => [],
                'requiredParametersAndOptionalPayload' => [],
                'requiredParametersAndRequiredPayload' => [],
            ],
            'PUT' => [
                'noParametersAndNoPayload' => [],
                'noParametersAndOptionalPayload' => [],
                'noParametersAndRequiredPayload' => [],
                'optionalParametersAndNoPayload' => [],
                'optionalParametersAndOptionalPayload' => [],
                'optionalParametersAndRequiredPayload' => [],
                'requiredParametersAndNoPayload' => [],
                'requiredParametersAndOptionalPayload' => [],
                'requiredParametersAndRequiredPayload' => [],
            ],
            'DELETE' => [
                'noParametersAndNoPayload' => [],
                'noParametersAndOptionalPayload' => [],
                'noParametersAndRequiredPayload' => [],
                'optionalParametersAndNoPayload' => [],
                'optionalParametersAndOptionalPayload' => [],
                'optionalParametersAndRequiredPayload' => [],
                'requiredParametersAndNoPayload' => [],
                'requiredParametersAndOptionalPayload' => [],
                'requiredParametersAndRequiredPayload' => [],
            ],
        ];

        $routeUrls = [
            'GET' => [
                'noPayload' => [],
                'optionalPayload' => [],
                'requiredPayload' => [],
            ],
            'POST' => [
                'noPayload' => [],
                'optionalPayload' => [],
                'requiredPayload' => [],
            ],
            'PUT' => [
                'noPayload' => [],
                'optionalPayload' => [],
                'requiredPayload' => [],
            ],
            'DELETE' => [
                'noPayload' => [],
                'optionalPayload' => [],
                'requiredPayload' => [],
            ],
        ];

        $strRoutesByName = $this->generateTypeDefinitionForRoutes($routes->whereNotNull('name')->groupBy('method'), $routeNames, $interfaceName, 'name');
        $strRoutesByUri = $this->generateTypeDefinitionForRoutes($routes->map(function ($item) {
            $uri = preg_replace_callback('/\{([^}]+)}/', function ($matches) {
                $placeholder = $matches[1];
                if (str_ends_with($placeholder, '?')) {
                    return '${string | null}';
                } else {
                    return '${string}';
                }
            }, $item['uri']);

            $item['uri'] = $uri;
            return $item;
        })->groupBy('method'), $routeUrls, $interfaceByUriName, 'uri');
        $str->append($strRoutesByName);
        $str->append('}')->append(PHP_EOL);
        $byUriStr->append($strRoutesByUri);
        $byUriStr->append('}')->append(PHP_EOL);

        // Joining byUriStr to real Str:
        // $str->append($byUriStr->toString());
        // $str->append(PHP_EOL);
        // Route Names Type
        $str->append("interface routeRequirementsByNames {")->append(PHP_EOL);

        foreach ($routeNames as $methodName => $names) {
            $str->append($this->tabSpace(1));
            $str->append(strtoupper($methodName) . ': ')->append('{');
            foreach ($names as $typeOfName => $namesList) {
                $str->append(PHP_EOL)
                    ->append($this->tabSpace(2));

                $str->append("'$typeOfName': ")
                    ->append(!empty($namesList) ? join(' | ', $namesList) : 'never');

            }
            $str->append(PHP_EOL)
                ->append($this->tabSpace(1))
                ->append('}')
                ->append(PHP_EOL);
        }
        $str->append('}')->append(PHP_EOL);


        // Route Uris Type
        $str->append("interface routeRequirementsByUris {")->append(PHP_EOL);

        foreach ($routeUrls as $methodName => $uris) {
            $str->append($this->tabSpace(1));
            $str->append(strtoupper($methodName) . ': ')->append('{');
            foreach ($uris as $typeOfName => $urisList) {
                $str->append(PHP_EOL)
                    ->append($this->tabSpace(2));

                $str->append("'$typeOfName': ")
                    ->append(!empty($urisList) ? join(' | ', array_map(function ($i) {
                        $result = preg_replace_callback('/\{([^}]+)}/', function ($matches) {
                            $placeholder = $matches[1];
                            if (str_ends_with($placeholder, '?')) {
                                return '${string | null}';
                            } else {
                                return '${string}';
                            }
                        }, $i);
                        return "`$result`";
                    }, $urisList)) : 'never');

            }
            $str->append(PHP_EOL)
                ->append($this->tabSpace(1))
                ->append('}')
                ->append(PHP_EOL);
        }
        $str->append('}')->append(PHP_EOL);


        return $str->toString();

        /*if (empty($methodsRoutesList)) {
            return 'never' . PHP_EOL;
        }
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($methodsRoutesList as $method => $names) {
            $typesResponsesStr .= $this->tabSpace(1) . $method . ': '
                . (Arr::join(Arr::map($names, fn ($i) => "'" . $i . "'"), ' | ') ?: 'never')
                . PHP_EOL;
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;*/
    }

    /**
     * @return array{isRequired: true|false, types: string[]}[]
     */
    private function getTypesFromRules(array|string $rules, Collection $allRules, $name): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        $isRequired = false;
        $types = [];
        foreach ($rules as $rule) {
            if (is_object($rule)) {
                $rule = class_basename($rule);
            }
            switch ($rule) {
                case 'required':
                case RequiredIf::class:
                    $isRequired = true;
                    break;
                case 'string':
                    $types[] = 'string';
                    break;
                case 'integer':
                case 'decimal':
                case 'float':
                case 'number':
                case 'numeric':
                    $types[] = 'number';
                    break;
                case 'boolean':
                    $types[] = 'boolean';
                    break;
                case 'nullable':
                    $types[] = 'null';
                    break;
                case 'array':

                    $ruleSubRules = $allRules->where(function ($_, $rule2) use ($name) {
                        return Str::startsWith($rule2, $name . '.*.');
                    });
                    if ($ruleSubRules->isNotEmpty()) {
                        $str = new AppendableString("{ ");
                        $str->append($ruleSubRules->map(function ($ruleSubRule, $ruleSubRuleKey) use ($name, &$isRequired, $rules) {
                            $definitions = $this->getTypesFromRules($ruleSubRule, collect($rules), $ruleSubRuleKey);
                            if ($definitions['isRequired'] === true) {
                                $isRequired = true;
                            } else {
                                $ruleSubRuleKey .= "?";
                            }
                            return str(Str::after($ruleSubRuleKey, $name . '.*.'))->append(": ")->append(join('& ', $definitions['types']))->toString();

                        })->join(", "));
                        $str->append(" }");
                        $types[] = $str->toString() . '[]';
                    } else {
                        $ruleFlatSubRules = $allRules->firstWhere(function ($_, $rule2) use ($name) {
                            return $rule2 === $name . '.*';
                        });

                        if ($ruleFlatSubRules) {
                            $str = new AppendableString("(");

                            $definitions = $this->getTypesFromRules($ruleFlatSubRules, collect($rules), ".*");
                            if ($definitions['isRequired'] === true) {
                                $isRequired = true;
                            }
                            $str->append(join(' & ', $definitions['types']));

                            $str->append(")");
                            $types[] = $str->toString() . '[]';
                        } else {
                            $types[] = 'any[]';
                        }

                    }
                    break;
                case 'object':
                    $types[] = "{ [p in string]: any }";
                    break;
            }
        }

        if (empty($types)) {
            $types = ['any'];
        }

        return ['isRequired' => $isRequired, 'types' => $types,];
    }


    private function parseMethodReturnTypeFromDocComment(ReflectionMethod $method): ?string
    {
        $docComment = $method->getDocComment();
        if (!$docComment) {
            return null;
        }

        $docBlock = $this->docBlockFactory->create($docComment);
        $varTag = $docBlock->getTagsByName('return')[0] ?? null;

        if (!$varTag) {
            return null;
        }

        $typeString = (string)$varTag;

        return $this->convertPhpDocTypeToTs($typeString);
    }

    private function convertPhpDocTypeToTs(string $phpDocType): string
    {

        if (Str::contains($phpDocType, '|')) {
            return collect(explode('|', $phpDocType))->map(fn($part) => $this->convertPhpDocTypeToTs($part))->unique()->join(" | ");
        }

        // Handle array of specific types
        if (Str::startsWith($phpDocType, 'array') && preg_match('/^array<([^,]+)>$/', $phpDocType, $matches)) {
            return $this->convertPhpDocTypeToTs($matches[1]) . '[]';
        }
        // Handle array of object
        if (Str::startsWith($phpDocType, 'array') && preg_match('/^array([^,]+)>$/', $phpDocType, $matches)) {
            return $this->convertPhpDocTypeToTs($matches[1]) . '[]';
        }

        if (Str::startsWith($phpDocType, 'array') && preg_match_all('/(\w+):\s*([\w\\\]+|\w+<[\w\\\]+>)/', $phpDocType, $matches, PREG_SET_ORDER)) {
            $tsProperties = [];

            foreach ($matches as $match) {
                $property = $match[1]; // Property name
                $phpType = $match[2];  // PHP type

                $tsType = $this->convertPhpDocTypeToTs($phpType);;

                $tsProperties[] = "$property: $tsType";
            }

            if (empty($tsProperties)) {
                return "{ }";
            }

            return "{ " . implode(", ", $tsProperties) . " }";
        }
        if (isset($this->phpToTsTypeMap[$phpDocType])) {
            return $this->phpToTsTypeMap[$phpDocType];
        } else {
            $converted = str($phpDocType)->replace('\ApiJsonResponse', 'ApiResponseData')->replace('<\\', '<')->toString();
            $betweenBrackets = str($converted)->between('<', '>')->toString();
            if (!empty($betweenBrackets) && Str::startsWith($betweenBrackets, 'array')) {
                $betweenBracketsIntoType = $this->convertPhpDocTypeToTs($betweenBrackets);
                $converted = str($converted)->replace($betweenBrackets, $betweenBracketsIntoType)->toString();
            }
            if (str($converted)->contains('ApiResponseData')) {
                if (!in_array('ApiResponseData', $this->typescriptTypesToImport)) {
                    $this->typescriptTypesToImport[] = 'ApiResponseData';
                }
                $dataType = str($converted)->between('<', '>')->toString();
                if (isset($this->phpToTsTypeMap[$dataType])) {
                    $converted = str($converted)->replace($dataType, $this->phpToTsTypeMap[$dataType])->toString();
                } else {
                    if (!empty($dataType) && !in_array($dataType, $this->typescriptTypesToImport) && Str::doesntContain($dataType, [' ', '{', '}', ':', '|'])) {
                        $this->typescriptTypesToImport[] = $dataType;
                    }
                }

            } else {
                $converted = Str::afterLast($converted, '\\');
                if (!empty($converted) && !in_array($converted, $this->typescriptTypesToImport) && Str::doesntContain($converted, [' ', '{', '}', ':', '|'])) {
                    $this->typescriptTypesToImport[] = $converted;
                }
            }
            if (in_array($converted, $this->typescriptTypesToImportIgnoreList)) {
                return "any";
            }
            return $converted;
        }

    }

    private function generateTypeDefinitionForRoutes(Collection $groups, array &$routeNamesOrUris, $interfaceName, string $useProperty = 'name'): string
    {
        $str = new AppendableString('');
        $groups->each(function (Collection $items, string $methodName) use ($useProperty, &$routeNamesOrUris, $interfaceName, $str) {

            $str->append($this->tabSpace(1));
            $str->append(strtoupper($methodName) . ': ')->append('{');
            if ($items->isNotEmpty()) {
                $str->append(PHP_EOL);
            } else {
                $str->append(' ');
            }
            $items->unique($useProperty)->each(function (array $item) use ($useProperty, &$routeNamesOrUris, $methodName, $interfaceName, $str) {
                $str->append($this->tabSpace(2));
                $str->append("'$item[$useProperty]': {")->append(PHP_EOL);
                // Parameters:

                $str->append($this->tabSpace(3))->append("routeParameters: ");
                $parameters = collect($item['parameters']);
                $parametersHasRequired = false;

                if ($parameters->isEmpty()) {
                    $str->append('never | null');
                } else {
                    $requiredCount = $parameters->where('isRequired', true)->count();
                    $parametersHasRequired = $requiredCount > 0;
                    if ($requiredCount === 1) {
                        $str->append('ParameterType | ');
                    } else if ($requiredCount === 0) {
                        $str->append('ParameterType | null | ');
                    }
                    for ($i = $requiredCount; $i <= $parameters->count(); $i++) {
                        $str->append('[ ');
                        for ($k = 0; $k < $i; $k++) {
                            $str->append('ParameterType');
                            if ($k < $i - 1) {
                                $str->append(', ');
                            }
                        }
                        $str->append(' ]');

                        if ($i < $parameters->count()) {
                            $str->append(' | ');
                        }
                    }
                    $str->append(' | { ');
                    $str->append($parameters->map(function ($parameter) {
                        return "$parameter[name]" . ($parameter['isRequired'] ? '' : '?') . ": ParameterType";
                    })->join(", "));
                    $str->append(' }');

                    $bindableParameters = $parameters->filter(function ($parameter) {
                        return !empty($parameter['binding']);
                    });
                    if ($bindableParameters->isNotEmpty()) {
                        $str->append(' | { ');
                        $str->append($bindableParameters->map(function ($parameter) {
                            return "$parameter[binding]" . ($parameter['isRequired'] ? '' : '?') . ": ParameterType";
                        })->join(", "));
                        $str->append(' }');
                    }
                }

                // Payload
                $str->append(PHP_EOL);

                $str->append($this->tabSpace(3))->append("payload: ");
                $rules = collect($item['rules']);
                $rulesHasRequired = false;
                if ($rules->isEmpty()) {
                    $str->append('never');
                } else {
                    $str->append("{")->append(PHP_EOL);

                    $rules->each(function (string|array $rule, $name) use ($rules, $str, &$rulesHasRequired) {

                        // This excludes array and nested objects
                        if (Str::contains($name, ['*', '.'])) {
                            return;
                        }
                        $str->append($this->tabSpace(4));

                        // $str->append("'$name'" . ': ');

                        $rulesDefinition = $this->getTypesFromRules($rule, $rules, $name);
                        if ($rulesDefinition['isRequired']) {
                            $rulesHasRequired = true;
                        } else {
                            $name = $name . "?";
                        }

                        $str->append("$name" . ': ');
                        $str->append(join('& ', $rulesDefinition['types']));
                        $str->append(PHP_EOL);
                    });
                    $str->append($this->tabSpace(3))->append("}");
                }
                //

                // AxiosConfigParams
                $str->append(PHP_EOL);

                $str->append($this->tabSpace(3))->append("payloadAsAxiosConfig: {")
                    ->append(PHP_EOL)
                    ->append($this->tabSpace(4))
                    ->append("params");

                if (!$rulesHasRequired) {
                    $str->append("?");
                }
                $str->append(": " . $interfaceName . "['$methodName']['" . $item[$useProperty] . "']['payload']");
                $str->append(PHP_EOL);
                $str->append($this->tabSpace(3))->append("}");


                // Response:

                $str->append(PHP_EOL);

                $str->append($this->tabSpace(3))->append("response: ");

                if (!empty($item['response'])) {
                    $str->append($item['response']);
                } else {
                    $str->append('any');
                }


                //

                $str->append(PHP_EOL)->append($this->tabSpace(2))->append('}');
                $str->append(PHP_EOL);

                if (!isset($routeNamesOrUris[$methodName])) {
                    $routeNamesOrUris[$methodName] = [];
                }

                $payloadStr = "AndNoPayload";
                if ($rulesHasRequired) {
                    $payloadStr = "AndRequiredPayload";
                } else if ($rules->isNotEmpty()) {
                    $payloadStr = "AndOptionalPayload";
                }

                $parametersStr = "noParameters";
                if ($parametersHasRequired) {
                    $parametersStr = "requiredParameters";
                } else if ($parameters->isNotEmpty()) {
                    $parametersStr = "optionalParameters";
                }

                if ($useProperty === 'name') {
                    if (!isset($routeNamesOrUris[$methodName][$parametersStr . $payloadStr])) {
                        $routeNamesOrUris[$methodName][$parametersStr . $payloadStr] = [];
                    }
                    $routeNamesOrUris[$methodName][$parametersStr . $payloadStr][] = "'$item[name]'";
                } else if ($useProperty === 'uri') {
                    $payloadStrWithoutAnd = str($payloadStr)->after('And')->lcfirst()->toString();
                    if (!isset($routeNamesOrUris[$methodName][$payloadStrWithoutAnd])) {
                        $routeNamesOrUris[$methodName][$payloadStrWithoutAnd] = [];
                    }
                    $routeNamesOrUris[$methodName][$payloadStrWithoutAnd][] = $item['uri'];
                }

            });
            $str->append($this->tabSpace(1));
            $str->append('}');
            $str->append(PHP_EOL);
        });

        return $str;
    }
}
