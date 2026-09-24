<?php

use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\EmployeeController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\UnguardedEmployeeController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Employee;
use HassanDomeDenea\HddLaravelHelpers\Tests\Policies\EmployeePolicy;
use HassanDomeDenea\HddLaravelHelpers\Tests\Policies\EmployeeWithAuditsPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('salary');
        $table->timestamps();
    });
    (include __DIR__.'/../../vendor/owen-it/laravel-auditing/database/migrations/audits.stub')->up();

    Gate::policy(Employee::class, EmployeePolicy::class);

    Route::apiResourceMany('employees', EmployeeController::class, ['audits']);
    Route::apiResourceMany('unguarded_employees', UnguardedEmployeeController::class, ['audits']);
});

function auditedEmployee(): Employee
{
    $employee = Employee::create(['name' => 'Ali', 'salary' => 1500]);
    $employee->audits()->create([
        'event' => 'created',
        'old_values' => [],
        'new_values' => ['name' => 'Ali', 'salary' => 1000],
    ]);
    $employee->audits()->create([
        'event' => 'updated',
        'old_values' => ['salary' => 1000],
        'new_values' => ['salary' => 1500],
    ]);

    return $employee;
}

/**
 * @param  list<string>  $permissions
 */
function auditsUser(array $permissions = [], bool $superAdmin = false): User
{
    return (new User)->forceFill(['id' => 1, 'permissions' => $permissions, 'super_admin' => $superAdmin]);
}

it('returns the history of a field to a user who may view the record', function () {
    $employee = auditedEmployee();

    $this->actingAs(auditsUser(['view_employees']))
        ->getJson("/employees/{$employee->id}/audits?field=salary")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.data');
});

it('forbids the history to a user who may not view the record', function () {
    $employee = auditedEmployee();

    $this->actingAs(auditsUser())
        ->getJson("/employees/{$employee->id}/audits?field=salary")
        ->assertForbidden();
});

it('also requires an app wide viewAudits ability once one is defined', function () {
    Gate::define('viewAudits', fn (User $user) => in_array('view_changes_history', $user->permissions, true));
    $employee = auditedEmployee();
    $uri = "/employees/{$employee->id}/audits?field=salary";

    $this->actingAs(auditsUser(['view_employees']))->getJson($uri)->assertForbidden();
    $this->actingAs(auditsUser(['view_changes_history']))->getJson($uri)->assertForbidden();
    $this->actingAs(auditsUser(['view_employees', 'view_changes_history']))->getJson($uri)->assertOk();
});

it('passes the requested field to a viewAudits policy method', function () {
    Gate::policy(Employee::class, EmployeeWithAuditsPolicy::class);
    $employee = auditedEmployee();

    $this->actingAs(auditsUser(['view_employees']))
        ->getJson("/employees/{$employee->id}/audits?field=name")
        ->assertOk();
    $this->actingAs(auditsUser(['view_employees']))
        ->getJson("/employees/{$employee->id}/audits?field=salary")
        ->assertForbidden();
    $this->actingAs(auditsUser(['view_employees', 'view_salaries']))
        ->getJson("/employees/{$employee->id}/audits?field=salary")
        ->assertOk();
});

it('lets a Gate::before override through both abilities', function () {
    Gate::before(fn (User $user) => $user->super_admin ? true : null);
    Gate::define('viewAudits', fn () => false);
    $employee = auditedEmployee();

    $this->actingAs(auditsUser(superAdmin: true))
        ->getJson("/employees/{$employee->id}/audits?field=salary")
        ->assertOk();
});

it('skips authorization for a controller without a policy class', function () {
    $employee = auditedEmployee();

    $this->actingAs(auditsUser())
        ->getJson("/unguarded_employees/{$employee->id}/audits?field=salary")
        ->assertOk();
});
