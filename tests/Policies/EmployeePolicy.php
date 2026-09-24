<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Policies;

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Employee;
use Illuminate\Foundation\Auth\User;

class EmployeePolicy
{
    public function view(User $user, Employee $employee): bool
    {
        return in_array('view_employees', $user->permissions ?? [], true);
    }
}
