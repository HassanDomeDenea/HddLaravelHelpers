<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

class Sort
{
    public string $direction;

    public function __construct(public string $field, string $direction = 'asc', int $order = 0)
    {
        $this->direction = $direction;
        if ($order !== 0) {
            $this->direction = $order === 1 ? 'asc' : 'desc';
        }

    }
}
