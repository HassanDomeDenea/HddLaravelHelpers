<?php

namespace HassanDomeDenea\HddLaravelHelpers\PrimeVueDataTableBackend;

use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 *
 * @template TData
 */
#[TypeScript('ResponseData<TData = any>')]
class ResponseData extends Data
{

    public function __construct(
        /**
         * @param TData[] $data
         */
        #[LiteralTypeScriptType('TData[]')]
        public Collection|\Illuminate\Support\Collection $data,
        public int                                       $current_page=0,
        public int                                       $from=0,
        public int                                       $to=0,
        public int                                       $per_page=0,
        public int                                       $last_page=0,
        public int                                       $total=0,
        public int                                       $total_without_filters=0,
        public array                                     $extra = []
    )
    {
    }
}
