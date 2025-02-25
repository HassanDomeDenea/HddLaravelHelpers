<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

class Sort
{
    public string $direction;

    public function __construct(public string $field, string $order)
    {
        $this->direction = (int) $order === 1 ? 'asc' : 'desc';

    }
}
