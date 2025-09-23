<?php

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Carbon\Carbon;
use DateTimeInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Resource;

class AuditData extends Resource
{
    public function __construct(
        public string|int      $id,
        public string          $event,
        public mixed           $oldValue,
        public mixed           $newValue,
        public DateTimeInterface          $createdAt,
        public ?AuditUserData  $user,
        public ?array          $oldValues = null,
        public ?array          $newValues = null,
        public ?string         $auditableType = null,
        public null|int|string $auditableId = null,
    )
    {

    }

}
