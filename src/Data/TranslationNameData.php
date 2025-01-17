<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Spatie\LaravelData\Data;

class TranslationNameData extends Data
{
    public function __construct(
        public ?string $ar,
        public ?string $en,
    ) {}
}
