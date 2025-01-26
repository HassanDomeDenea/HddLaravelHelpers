<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Spatie\LaravelData\Data;

class BatchResponseData extends Data
{
    public function __construct(
        public int   $successRequests,
        public int   $failedRequests,
        public bool  $allAreSuccessful,
        public bool  $allAreFailed,
        public array $responses,
    )
    {
    }
}
