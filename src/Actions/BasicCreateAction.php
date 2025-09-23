<?php
declare(strict_types=1);
namespace HassanDomeDenea\HddLaravelHelpers\Actions;

use Illuminate\Support\Fluent;
use Spatie\LaravelData\Data;

abstract class BasicCreateAction
{
    public function handle(Data|Fluent|array|null $attributes)
    {

    }
}
