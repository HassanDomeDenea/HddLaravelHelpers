<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Policies;

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Technician;
use Illuminate\Foundation\Auth\User;

/**
 * A user may only change the technicians listed in their `technician_ids`.
 */
class TechnicianPolicy
{
    public function update(User $user, Technician $technician): bool
    {
        return in_array($technician->id, $user->technician_ids ?? [], true);
    }

    public function delete(User $user, Technician $technician): bool
    {
        return in_array($technician->id, $user->technician_ids ?? [], true);
    }
}
