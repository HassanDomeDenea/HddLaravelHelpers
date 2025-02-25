<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class BatchResponseData extends Data
{
    /**
     * @param  Collection<BatchResponseItemData>  $responses
     */
    public function __construct(
        public int $successRequests,
        public int $failedRequests,
        public bool $allAreSuccessful,
        public bool $allAreFailed,
        public Collection $responses,
    ) {}
}
