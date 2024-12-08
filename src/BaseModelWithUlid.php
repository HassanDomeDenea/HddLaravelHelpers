<?php

namespace HassanDomeDenea\HddLaravelHelpers;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class BaseModelWithUlid extends BaseModel
{
    use HasUlids;
}
