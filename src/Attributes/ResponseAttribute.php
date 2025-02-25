<?php

namespace HassanDomeDenea\HddLaravelHelpers\Attributes;

use Attribute;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Spatie\LaravelData\Data;

#[Attribute(Attribute::TARGET_METHOD)]
class ResponseAttribute
{
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

    public string $controllerClassName;
    public string $controllerMethodName;

    /**
     * @param class-string<Data> $responseDataClassName
     * @param array|string|int|float|bool|null $properties
     * @param bool $wrapInApiResponseData
     */
    public function __construct(public string $responseDataClassName, public array|null|string|int|float|bool $properties = [], public bool $wrapInApiResponseData = true)
    {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    public function setContext(string $className, string $methodName): void
    {
        $this->controllerClassName = $className;
        $this->controllerMethodName = $methodName;
    }

    public function classNameToTypeScript(): string|null
    {
        if (!$this->responseDataClassName) {
            return null;
        }
        $isModular = str(config('typescript-transformer.writer'))->endsWith('ModuleWriter');
        if ($isModular) {
            return class_basename($this->responseDataClassName);
        } else {
            return Str::replace('\\', '.', $this->responseDataClassName);
        }
    }
}
