<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

class ResponseData extends Data
{
    public function __construct(
        public Collection|\Illuminate\Support\Collection $data,
        public int $current_page,
        public int $from,
        public int $to,
        public int $per_page,
        public int $last_page,
        public int $total,
        public int $total_without_filters,
        public array $extra = []
    ) {}
}
