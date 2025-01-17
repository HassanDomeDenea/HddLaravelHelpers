<?php

namespace HassanDomeDenea\HddLaravelHelpers\Attributes;

use Attribute;
use Illuminate\Support\Str;

#[Attribute]
class ResponseAttribute
{
    public function __construct(public string $className, public array $extraProperties = [])
    {

    }

    public function classNameToTypeScript(): string
    {
        return Str::replace('\\', '.', $this->className);
    }
}
