<?php

namespace HassanDomeDenea\HddLaravelHelpers;

use HassanDomeDenea\HddLaravelHelpers\Requests\StoreManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Requests\UpdateManyRequest;
use HassanDomeDenea\HddLaravelHelpers\Rules\EnsureEveryIdExistsRule;
use HassanDomeDenea\HddLaravelHelpers\Rules\ModelExistsRule;
use HassanDomeDenea\HddLaravelHelpers\Traits\HasCreateAndDeleteMany;
use HassanDomeDenea\HddLaravelHelpers\Traits\HasDeletableCheck;
use HassanDomeDenea\HddLaravelHelpers\Traits\HasFactoryMethods;
use HassanDomeDenea\HddLaravelHelpers\Traits\HasModelRules;
use HassanDomeDenea\HddLaravelHelpers\Traits\HasReordering;
use HassanDomeDenea\HddLaravelHelpers\Traits\TransformsToData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;
use Throwable;

class BaseModel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;
    use TransformsToData;
    use HasDeletableCheck;
    use HasCreateAndDeleteMany;
    use HasModelRules;
    use HasFactoryMethods;

}
