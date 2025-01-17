<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use HassanDomeDenea\HddLaravelHelpers\Attributes\RequestBodyAttribute;
use HassanDomeDenea\HddLaravelHelpers\Attributes\ResponseAttribute;
use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\RequiredIf;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class GenerateRoutesTypesCommand extends Command
{
    protected $signature = 'generate-routes-types {--url=}';

    protected $description = 'Convert Routes To Typescript file';

    protected Filesystem $files;

    private bool $useEslint = true;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function tabSpace(int $number, int $spaces = 2): string
    {
        $spacesPerTab = str_repeat(' ', $spaces);

        return str_repeat($spacesPerTab, $number);
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $path = base_path($this->option('url') ?: 'src/types/laravelRoutes.ts');

        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }

        $this->files->put($path, $this->getRoutesTypesOutput());

        if ($this->useEslint && strlen(shell_exec('bun -v')) < 20) {
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
        $routes = collect(Route::getRoutes())->reduce(/**
     * @throws ReflectionException
     */ function ($carry, \Illuminate\Routing\Route $route) use ($allowedMethods) {
            if (! Str::startsWith($route->uri(), 'api') && ! Str::startsWith($route->uri(), 'sanctum')) {
                return $carry;
            }
            $uri = $route->uri();
            if (Str::startsWith($uri, ['api', '/api'])) {
                $uri = Str::after($uri, 'api/');
            } else {
                $uri = Str::start($uri, '/');
            }
            if (empty($uri)) {
                return $carry;
            }
            foreach ($route->methods() as $method) {
                if (! in_array($method, $allowedMethods)) {
                    continue;
                }
                $allParameters = $route->signatureParameters();

                $controllerClassName = $route->getControllerClass();
                $controllerMethodName = $route->getActionMethod();

                $methodReflection = null;
                if (class_exists($controllerClassName) && method_exists($controllerClassName, $controllerMethodName)) {
                    $methodReflection = new ReflectionMethod($controllerClassName, $controllerMethodName);
                }

                $rulesList = [];
                $rules = [];
                if ($attributes = $methodReflection?->getAttributes(RequestBodyAttribute::class)) {
                    foreach ($attributes as $attribute) {
                        $rulesList[] = $attribute->newInstance()->getRules();
                        $rules = $rules + $attribute->newInstance()->getRules();
                    }
                }
                foreach ($allParameters as $parameter) {
                    $model = Reflector::getParameterClassName($parameter) ?: $parameter->getType();
                    if (class_exists($model)) {
                        $reflectionClass = new ReflectionClass($model);
                        if ($reflectionClass->hasMethod('rules')) {
                            $rulesList[] = $reflectionClass->newInstance()->rules();
                            $rules = $rules + $reflectionClass->newInstance()->rules();
                        }
                    }
                }

                $bindings = [];
                $parameters = $route->signatureParameters(['subClass' => UrlRoutable::class]);
                foreach ($parameters as $parameter) {
                    $model = Reflector::getParameterClassName($parameter) ?: $parameter->getType();
                    if (empty($model)) {
                        continue;
                    }
                    $reflectionClass = new ReflectionClass($model);

                    $bindings[$parameter->name] = $reflectionClass->hasMethod('getRouteKeyName') ? app($model)->getRouteKeyName() : null;
                }
                $response = null;
                if ($attributes = $methodReflection?->getAttributes(ResponseAttribute::class)) {
                    $response = $attributes[0]->newInstance()->classNameToTypeScript();
                }
                if ($response === 'App.PrimeVueDataTableBackend.ResponseData' && $route->getName()) {
                    $carry['primeVueDatatableRouteNames'][] = $route->getName();
                    $carry['primeVueDatatableDeleteRouteNames'][] = str_replace('.index', '.destroy', $route->getName());
                    $carry['primeVueDatatableRoutePaths'][] = $uri;
                }

                $parametersTypes = [];
                foreach ($route->parameterNames() as $name) {
                    if (Str::contains($route->uri(), '{' . $name . '?}')) {
                        $parametersTypes[$name . '?'] = 'string | number';
                    } else {
                        $parametersTypes[$name] = 'string | number';
                    }
                }
                Arr::first($parametersTypes, fn ($v, $k) => Str::endsWith($k, '?'));

                if ($route->getName()) {
                    if (! empty($rules)) {
                        if (empty($parametersTypes)) {
                            $carry['namesWithEmptyParametersWithBody'][$method][] = $route->getName();
                        } elseif (! Arr::first($parametersTypes, fn ($v, $k) => ! Str::endsWith($k, '?'))) {
                            $carry['namesWithAllOptionalParametersWithBody'][$method][] = $route->getName();
                        } else {
                            $carry['namesWithSomeRequiredParametersWithBody'][$method][] = $route->getName();
                        }
                    }
                    if (empty($rules) || ! $this->hasRequiredRule($rules)) {
                        if (empty($parametersTypes)) {
                            $carry['namesWithEmptyParametersEmptyBody'][$method][] = $route->getName();
                        } elseif (! Arr::first($parametersTypes, fn ($v, $k) => ! Str::endsWith($k, '?'))) {
                            $carry['namesWithAllOptionalParametersEmptyBody'][$method][] = $route->getName();
                        } else {
                            $carry['namesWithSomeRequiredParametersEmptyBody'][$method][] = $route->getName();
                        }
                    }
                }
                $carry['values'][$method][$route->getName() ?: $route->uri()] = [
                    'url' => $uri,
                    'name' => $route->getName(),
                    'parameters' => $route->parameterNames(),
                    'required_parameters' => $requiredParameters = Arr::where($route->parameterNames(), fn ($i) => ! Str::contains($route->uri(), '{' . $i . '?}')),
                    'bindings' => array_flip($bindings) ?: null,
                    'required_bindings' => $bindings ? array_keys(Arr::where($bindings, fn ($v, $k) => in_array($k, $requiredParameters))) : null,
                    'needsBody' => ! empty($rules),
                ];
                $carry['types'][$method][$route->getName() ?: $route->uri()] = [
                    'url' => $uri,
                    'name' => $route->getName(),
                    'parameters' => $parametersTypes,
                    'response' => $response,
                    'body' => $rules,
                    'rulesList' => $rulesList,
                    'bindings' => $bindings,
                ];
            }

            return $carry;
        }, [
            'primeVueDatatableRoutePaths' => [],
            'primeVueDatatableRouteNames' => [],
            'primeVueDatatableDeleteRouteNames' => [],
            'namesWithEmptyParametersEmptyBody' => ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []],
            'namesWithAllOptionalParametersEmptyBody' => ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []],
            'namesWithSomeRequiredParametersEmptyBody' => ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []],
            'namesWithEmptyParametersWithBody' => ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []],
            'namesWithAllOptionalParametersWithBody' => ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []],
            'namesWithSomeRequiredParametersWithBody' => ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []],
        ]);

        $values = json_encode($routes['values'], JSON_PRETTY_PRINT) ?: '';

        $typesStr = $this->getLaravelRoutesConst($routes);

        $methodsDeclarations = $this->getMethodsDeclarations($allowedMethods);

        $routeItemByNameResponseType = $this->getRouteItemByNameResponseType($routes);
        $routeItemByUrlResponseType = $this->getRouteItemByUrlResponseType($routes);

        $routeItemByNameBodyType = $this->getRouteItemByNameBodyType($routes);
        $routeItemByUrlBodyType = $this->getRouteItemByUrlBodyType($routes);
        $namesWithSomeRequiredParametersEmptyBody = $this->generateRouteNameByMethodsListFromArray($routes['namesWithSomeRequiredParametersEmptyBody']);
        $namesWithEmptyParametersEmptyBody = $this->generateRouteNameByMethodsListFromArray($routes['namesWithEmptyParametersEmptyBody']);
        $namesWithAllOptionalParametersEmptyBody = $this->generateRouteNameByMethodsListFromArray($routes['namesWithAllOptionalParametersEmptyBody']);

        $namesWithSomeRequiredParametersWithBody = $this->generateRouteNameByMethodsListFromArray($routes['namesWithSomeRequiredParametersWithBody']);
        $namesWithEmptyParametersWithBody = $this->generateRouteNameByMethodsListFromArray($routes['namesWithEmptyParametersWithBody']);
        $namesWithAllOptionalParametersWithBody = $this->generateRouteNameByMethodsListFromArray($routes['namesWithAllOptionalParametersWithBody']);
        $primeVueDatatableRouteNames = Arr::join(Arr::map($routes['primeVueDatatableRouteNames'], fn ($i) => "'" . $i . "'"), ' | ');
        $primeVueDatatableDeleteRouteNames = Arr::join(Arr::map($routes['primeVueDatatableDeleteRouteNames'], fn ($i) => "'" . $i . "'"), ' | ');
        $primeVueDatatableRoutePaths = Arr::join(Arr::map($routes['primeVueDatatableRoutePaths'], fn ($i) => "'" . $i . "'"), ' | ');

        return <<<JAVASCRIPT
/* This file is auto generated */
import type { AxiosError, AxiosRequestConfig, AxiosResponse } from 'axios'
import { apiAxiosInstance } from '~/composables/axios'

type RouteMethodType = 'GET' | 'POST' | 'PUT' | 'DELETE'
type RouteNameType = string | null
type RoutePathType = string
type RouteParameterNameType = string
type RouteBindingsType = { [p in string]: string } | null
type RouteItemType = {
  name: RouteNameType
  url: RoutePathType
  parameters: RouteParameterNameType[]
  required_parameters: RouteParameterNameType[]
  bindings: RouteBindingsType
  required_bindings: RouteParameterNameType[] | null
  needsBody: boolean
  method?: RouteMethodType
}
type RouteListByMethodType = {
  [p in RouteMethodType]: {
    [p2 in (RouteNameType | RoutePathType) & string]: RouteItemType
  }
}

type RouteNamesWithSomeRequiredParametersEmptyBody = $namesWithSomeRequiredParametersEmptyBody

type RouteNamesWithEmptyParametersEmptyBody = $namesWithEmptyParametersEmptyBody

/*
type RouteNamesWithAllOptionalParametersEmptyBody = $namesWithAllOptionalParametersEmptyBody
*/

type RouteNamesWithSomeRequiredParametersWithBody = $namesWithSomeRequiredParametersWithBody

type RouteNamesWithEmptyParametersWithBody = $namesWithEmptyParametersWithBody

/*
type RouteNamesWithAllOptionalParametersWithBody = $namesWithAllOptionalParametersWithBody
*/

export type RouteItemByNameResponseType = $routeItemByNameResponseType

export type RouteItemByUrlResponseType = $routeItemByUrlResponseType

export type RouteItemByNameBodyType = $routeItemByNameBodyType

export type RouteItemByUrlBodyType = $routeItemByUrlBodyType

export type PrimeVueDatatableRouteNames = $primeVueDatatableRouteNames

export type PrimeVueDatatableDeleteRouteNames = $primeVueDatatableDeleteRouteNames

export type PrimeVueDatatableRoutePaths = $primeVueDatatableRoutePaths

export type LaravelRoutesTypes = $typesStr

export type ApiResponse<T> = {
  success: boolean
  data: T
}

export type AxiosApiValidationError<T = any> = {
  response: Omit<AxiosResponse<T>, 'data'> & {
    data: {
      message: string
      errors: string[]
    }
  }
} & AxiosError

function isAxiosConfig(config: any): boolean {
  return config && (config.params || config.baseURL)
}

const laravelRoutes: RouteListByMethodType = $values

type SplitIntoUnion<T> = {
  [K in keyof T]: { [P in K]: T[K] }
}[keyof T]

type ConvertBindingsToObjects<T extends { [p: string]: any }, K extends { [p: string]: any }> = keyof T extends keyof K ? SplitIntoUnion<{ [P in T[ keyof T]]: K[keyof T] }> & { [key: string]: any } : never

$methodsDeclarations

JAVASCRIPT . <<<'JS'

function generateRouteUrl(route: RouteItemType, param: any): string {
  let url = route.url
  if (route.parameters.length > 0 && param) {
    if (typeof param === 'object') {
      if (Array.isArray(param)) {
        for (let i = 0; i < route.parameters.length; i++) {
          if (param[i] !== undefined)
            url = replaceParamWithValue(url, route.parameters[i], param[i])
        }
      }
      else {
        for (const i in param) {
          if (route.parameters.includes(i))
            url = replaceParamWithValue(url, i, param[i])
          else if (route.bindings && route.bindings[i])
            url = replaceParamWithValue(url, route.bindings[i], param[i])
        }
      }
    }
    else {
      url = replaceParamWithValue(url, route.parameters[0], param)
    }
  }
  return url
}

function replaceParamWithValue(url: string, param: undefined | string, value: any): string {
  url = url.replace(`{${param}}`, value)
  url = url.replace(`{${param}?}`, value)
  return url
}


export const api = {
  get<T extends keyof RouteItemByUrlResponseType['GET'] >(url: T | string, config?: {
    params?: RouteItemByUrlBodyType['GET'][T] | string | any
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByUrlResponseType['GET'][T]>>> {
    return apiAxiosInstance.get(url as string, config)
  },
  post<T extends keyof RouteItemByUrlResponseType['POST']>(url: T | string, data?: RouteItemByUrlBodyType['POST'][T], config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<RouteItemByUrlResponseType['POST'][T]>>> {
    return apiAxiosInstance.post(url, data, config)
  },
  put<T extends keyof RouteItemByUrlResponseType['PUT']>(url: T | string, data: RouteItemByUrlBodyType['PUT'][T], config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<RouteItemByUrlResponseType['PUT'][T]>>> {
    return apiAxiosInstance.put(url, data, config)
  },
  delete<T extends keyof RouteItemByUrlResponseType['DELETE']>(url: T | string, config?: {
    params?: RouteItemByUrlBodyType['DELETE'][T]
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByUrlResponseType['DELETE'][T]>>> {
    return apiAxiosInstance.delete(url as string, config)
  },
  getByName: axiosGetByName,
  deleteByName: axiosDeleteByName,
  postByName: axiosPostByName,
  putByName: axiosPutByName,
}


JS;

    }

    private function getRouteItemByNameResponseType(array $routes): string
    {
        $typesResponsesStr = '{' . PHP_EOL;
        foreach ($routes['types'] as $paths) {
            foreach ($paths as $pathData) {
                if (! $pathData['name']) {
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
                if (! empty($pathData['parameters'])) {

                    //Object Type
                    $typesStr .= $this->tabSpace(3) . 'parameters: {' . PHP_EOL;

                    foreach ($pathData['parameters'] as $parameterName => $parameterType) {
                        $typesStr .= $this->tabSpace(4) . $parameterName . ': ' . $parameterType . PHP_EOL;
                    }
                    $typesStr .= $this->tabSpace(3) . '}' . PHP_EOL;

                    //Array Type
                    $optionals = Arr::where(array_keys($pathData['parameters']), fn ($i) => Str::endsWith($i, '?'));
                    $typesStr .= $this->tabSpace(3) . 'parameters_array: [';
                    $typesStr .= Arr::join($pathData['parameters'], ', ');
                    foreach ($optionals as $n => $optional) {
                        $typesStr .= '] | [' . Arr::join(Arr::take($pathData['parameters'], count($pathData['parameters']) - $n), ', ');
                    }
                    $typesStr .= ']' . PHP_EOL;

                    //Single Type
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
                if (! empty($pathData['bindings'])) {

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

function processPostPutRoutesArgs(routeObject: RouteItemType, args: any) {
  let config, body, routeParameters
  if (args.length > 0) {
    if (routeObject.parameters.length) {
      routeParameters = args[0]
      if (routeObject.needsBody) {
        body = args[1]
        config = args[2]
      }
      else {
        config = args[1]
      }
    }
    else if (routeObject.needsBody) {
      body = args[0]
      config = args[1]
    }
    else {
      config = args[0]
    }
  }
  return {
    config,
    body,
    routeParameters,
  }
}

TS;
        foreach ($allowedMethods as $method) {
            $smallLetterMethod = strtolower($method);
            $methodsDeclarations .=
              <<<TYPESCRIPT

type {$smallLetterMethod}EndpointPath = string

export function {$smallLetterMethod}RoutePath<T extends RouteNamesWithEmptyParametersEmptyBody['$method']>(name: T): {$smallLetterMethod}EndpointPath
export function {$smallLetterMethod}RoutePath<T extends keyof LaravelRoutesTypes['$method'], K extends LaravelRoutesTypes['$method'][T]['parameters'], B extends LaravelRoutesTypes['$method'][T]['bindings']>(name: T, param: Array<K[keyof K]>[number] | K | ConvertBindingsToObjects<B, K>): {$smallLetterMethod}EndpointPath

export function {$smallLetterMethod}RoutePath<T extends keyof LaravelRoutesTypes['$method'], K extends LaravelRoutesTypes['$method'][T]['parameters'], B extends LaravelRoutesTypes['$method'][T]['bindings']>(name: T, param?:  Array<K[keyof K]>[number] | K | ConvertBindingsToObjects<B, K>): {$smallLetterMethod}EndpointPath {
  return generateRouteUrl(laravelRoutes.{$method}[name] as RouteItemType, param)
}

TYPESCRIPT;
            if (in_array($method, ['GET', 'DELETE'])) {
                $ucFirstMethodName = ucfirst($smallLetterMethod);
                $methodsDeclarations .= <<<TS

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithSomeRequiredParametersEmptyBody['{$method}'], P extends LaravelRoutesTypes['{$method}'][N]['parameters'], B extends LaravelRoutesTypes['{$method}'][N]['bindings']>(
  name: N,
  routeParameter: LaravelRoutesTypes['{$method}'][N]['parameters_single'] | P | LaravelRoutesTypes['{$method}'][N]['parameters_array'] | ConvertBindingsToObjects<B, P>,
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithSomeRequiredParametersWithBody['{$method}'], P extends LaravelRoutesTypes['{$method}'][N]['parameters'], B extends LaravelRoutesTypes['{$method}'][N]['bindings']>(
  name: N,
  routeParameter: LaravelRoutesTypes['{$method}'][N]['parameters_single'] | P | LaravelRoutesTypes['{$method}'][N]['parameters_array'] | ConvertBindingsToObjects<B, P>,
  config: {
    params: RouteItemByNameBodyType[N]
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithEmptyParametersEmptyBody['{$method}']>(
  name: N,
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithEmptyParametersWithBody['{$method}']>(
  name: N,
  config: {
    params: RouteItemByNameBodyType[N]
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithEmptyParametersWithBody['{$method}'] | RouteNamesWithSomeRequiredParametersWithBody['{$method}']
  | RouteNamesWithEmptyParametersEmptyBody['{$method}'] | RouteNamesWithSomeRequiredParametersEmptyBody['{$method}']>(
  name: N,
  ...args: any[]

): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>> {
  let url
  if (args[1] || (args[0] && !isAxiosConfig(args[0])))
    url = {$smallLetterMethod}RoutePath(name, args[0])
  else
    url = {$smallLetterMethod}RoutePath(name as RouteNamesWithEmptyParametersEmptyBody['{$method}'])

  const config = args[1] || args[0]
  return apiAxiosInstance.{$smallLetterMethod}(url, config)
}

TS;

            }
            if (in_array($method, ['POST', 'PUT'])) {
                $ucFirstMethodName = ucfirst($smallLetterMethod);
                $methodsDeclarations .= <<<TS

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithSomeRequiredParametersEmptyBody['$method'], P extends LaravelRoutesTypes['$method'][N]['parameters'], B extends LaravelRoutesTypes['$method'][N]['bindings']>(
  name: N,
  routeParameter: LaravelRoutesTypes['$method'][N]['parameters_single'] | P | LaravelRoutesTypes['$method'][N]['parameters_array'] | ConvertBindingsToObjects<B, P>,
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithSomeRequiredParametersWithBody['$method'], P extends LaravelRoutesTypes['$method'][N]['parameters'], B extends LaravelRoutesTypes['$method'][N]['bindings']>(
  name: N,
  routeParameter: LaravelRoutesTypes['$method'][N]['parameters_single'] | P | LaravelRoutesTypes['$method'][N]['parameters_array'] | ConvertBindingsToObjects<B, P>,
  body: RouteItemByNameBodyType[N],
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithEmptyParametersEmptyBody['$method']>(
  name: N,
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithEmptyParametersWithBody['$method']>(
  name: N,
  body: RouteItemByNameBodyType[N],
  config?: {
    baseURL?: string
  }): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>>

export function axios{$ucFirstMethodName}ByName<N extends RouteNamesWithEmptyParametersWithBody['$method'] | RouteNamesWithSomeRequiredParametersWithBody['$method']
  | RouteNamesWithEmptyParametersEmptyBody['$method'] | RouteNamesWithSomeRequiredParametersEmptyBody['$method']>(
  name: N,
  ...args: any[]
): Promise<AxiosResponse<ApiResponse<RouteItemByNameResponseType[N]>>> {
  const routeObject = laravelRoutes.{$method}[name] as RouteItemType
  const { config, body, routeParameters } = processPostPutRoutesArgs(routeObject, args)

  return apiAxiosInstance.{$smallLetterMethod}({$smallLetterMethod}RoutePath(name, routeParameters), body, config)
}
TS;
            }

        }

        $methodsDeclarations .= false === config('hdd-laravel-helpers.routes_types_methods_declarations',false) ? '' : <<<'TS'

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
            return $rootPrefix . 'never' . (! empty($rootPrefix) ? PHP_EOL : '');
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
                        if (! empty($x)) {
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
        $bodyRules .= (! empty($rootPrefix) ? PHP_EOL : '');

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
                if (! $pathData['name']) {
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
              . (Arr::join(Arr::map($names, fn ($i) => "'" . $i . "'"), ' | ') ?: 'never')
              . PHP_EOL;
        }

        $typesResponsesStr .= '}';

        return $typesResponsesStr;
    }
}
