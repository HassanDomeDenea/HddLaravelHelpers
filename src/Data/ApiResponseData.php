<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 *
 * @template TData
  */
#[MapOutputName(SnakeCaseMapper::class)]
#[TypeScript('ApiResponseData<TData = any>')]
class ApiResponseData extends Data
{

    /**
     * @param bool $success
     * @param TData $data
     */
    public function __construct(
        public bool  $success,

        #[LiteralTypeScriptType('TData')]
        public mixed $data,
    )
    {
    }

}
