<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data\Requests;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class ReorderRequestData extends Data
{
    public function __construct(
        public int $from_order,
        public int $to_order,
        public ?array $scopedValues = null,
    )
    {
    }
}
