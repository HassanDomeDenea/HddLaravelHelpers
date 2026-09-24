<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Employee extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'name',
        'salary',
    ];
}
