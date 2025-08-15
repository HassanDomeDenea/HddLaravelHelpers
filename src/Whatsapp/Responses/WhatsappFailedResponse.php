<?php

namespace HassanDomeDenea\HddLaravelHelpers\Whatsapp\Responses;

use Spatie\LaravelData\Data;

class WhatsappFailedResponse extends Data
{

    public bool $success = false;
    public function __construct(public ?string $message)
    {
    }
}
