<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Data\Worker;

use HassanDomeDenea\HddLaravelHelpers\Tests\Data\Category\CategoryData;
use HassanDomeDenea\HddLaravelHelpers\Tests\Data\Tag\TagData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class WorkerData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $category_id,
        public ?CategoryData $category = null,
        #[DataCollectionOf(TagData::class)]
        public ?DataCollection $tags = null,
    ) {}
}
