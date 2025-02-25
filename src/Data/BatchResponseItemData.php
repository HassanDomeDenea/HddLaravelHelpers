<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Spatie\LaravelData\Data;

class BatchResponseItemData extends Data
{
    public function __construct(
        public int $status_code,
        public mixed $content,
    ) {}
}
