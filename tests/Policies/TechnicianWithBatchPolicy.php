<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Policies;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User;

/**
 * Decides a batch as a whole: small batches only, whoever owns the records.
 */
class TechnicianWithBatchPolicy extends TechnicianPolicy
{
    public function updateMany(User $user, Collection $technicians): bool
    {
        return $technicians->count() <= 2;
    }

    public function deleteMany(User $user, Collection $technicians): bool
    {
        return $technicians->count() <= 2;
    }
}
