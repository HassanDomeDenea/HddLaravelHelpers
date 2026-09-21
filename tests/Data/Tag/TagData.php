<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Data\Tag;

use Spatie\LaravelData\Data;

class TagData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $worker_id,
    ) {}
}
