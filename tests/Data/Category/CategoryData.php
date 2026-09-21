<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Data\Category;

use Spatie\LaravelData\Data;

class CategoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
