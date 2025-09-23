<?php
declare(strict_types=1);
namespace HassanDomeDenea\HddLaravelHelpers\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Fluent;
use Spatie\LaravelData\Data;

abstract class BasicUpdateAction
{
    public function handle(Model $modelInstance, Data|Fluent|array|null $attributes)
    {

    }
}
