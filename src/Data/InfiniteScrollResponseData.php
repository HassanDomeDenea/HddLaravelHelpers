<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class InfiniteScrollResponseData extends Data
{
    public function __construct(
        public array|Collection $items,
        public int $total,
    ) {
    }
}
