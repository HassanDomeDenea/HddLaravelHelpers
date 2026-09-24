<?php

namespace HassanDomeDenea\HddLaravelHelpers\Tests\Policies;

use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Employee;
use Illuminate\Foundation\Auth\User;

/**
 * Keeps the salary history behind its own permission while every other field only needs `view`.
 */
class EmployeeWithAuditsPolicy extends EmployeePolicy
{
    public function viewAudits(User $user, Employee $employee, string $field): bool
    {
        return $field !== 'salary' || in_array('view_salaries', $user->permissions ?? [], true);
    }
}
