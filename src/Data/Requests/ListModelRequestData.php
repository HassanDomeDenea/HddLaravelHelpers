<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data\Requests;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class ListModelRequestData extends Data
{
    public function __construct(
        public string|Optional|null $plucked,
        public string|Optional|null $pluckBy,
        public string|Optional|null $orderBy,
        #[In(['asc', 'desc'])]
        public string|Optional|null $orderByDirection,
        public string|Optional|null $filterBy,
        public string|int|bool|Optional $filterValue,
    )
    {
    }
}
