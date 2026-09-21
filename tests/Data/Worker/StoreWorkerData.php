<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Data\Worker;

use Spatie\LaravelData\Data;

class StoreWorkerData extends Data
{
    public function __construct(
        public string $name,
        public int $category_id,
    ) {}
}
