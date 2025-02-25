<?php

namespace HassanDomeDenea\HddLaravelHelpers\Helpers;

class AppendableString
{
    public function __construct(protected string $value = '')
    {
    }

    /**
     * @param string[] $values
     * @return $this
     */
    public function append(...$values): self
    {
        $flattenedValues = [];
        array_walk_recursive($values, function ($v) use (&$flattenedValues) {
            $flattenedValues[] = (string) $v;
        });

        $this->value .= join('', $flattenedValues);
        return $this;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
