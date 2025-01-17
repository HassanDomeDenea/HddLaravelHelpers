<?php

namespace HassanDomeDenea\HddLaravelHelpers\Attributes;

use Attribute;
use Illuminate\Support\Str;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class RequestBodyAttribute
{
    public bool $isClassName = true;

    private ?string $className = null;

    private ?array $rulesArray = null;

    /**
     * @param  string|array<string,string|string[]>  $classNameOrRulesArray
     */
    public function __construct(string|array $classNameOrRulesArray)
    {
        if (is_string($classNameOrRulesArray)) {
            $this->isClassName = true;
            $this->className = $classNameOrRulesArray;
        } else {
            $this->isClassName = false;
            $this->rulesArray = $classNameOrRulesArray;
        }

    }

    public function getCassNameToTypeScript(): string|array
    {

        if (class_exists($this->className) && method_exists($this->className, 'GetRulesForAttributes')) {
            return call_user_func([$this->className, 'GetRulesForAttributes']);
        }

        return Str::replace('\\', '.', $this->className);
    }

    public function getRules(): array|string
    {
        return $this->isClassName ? $this->getCassNameToTypeScript() : $this->rulesArray;
    }
}
