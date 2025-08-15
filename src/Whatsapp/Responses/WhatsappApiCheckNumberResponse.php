<?php

namespace HassanDomeDenea\HddLaravelHelpers\Whatsapp\Responses;

use Spatie\LaravelData\Data;

class WhatsappApiCheckNumberResponse extends Data
{
    public bool $success=true;
    public function __construct( public bool $hasWhatsapp, public string $contactName)
    {

    }
}
