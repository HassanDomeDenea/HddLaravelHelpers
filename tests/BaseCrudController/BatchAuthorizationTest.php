<?php

use HassanDomeDenea\HddLaravelHelpers\Tests\Controllers\TechnicianController;
use HassanDomeDenea\HddLaravelHelpers\Tests\Models\Technician;
use HassanDomeDenea\HddLaravelHelpers\Tests\Policies\TechnicianPolicy;
use HassanDomeDenea\HddLaravelHelpers\Tests\Policies\TechnicianWithBatchPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('technicians', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });

    Gate::policy(Technician::class, TechnicianPolicy::class);

    Route::apiResourceMany('technicians', TechnicianController::class, ['updateMany', 'destroyMany']);

    $this->first = Technician::create(['name' => 'Ali']);
    $this->second = Technician::create(['name' => 'Hassan']);
    $this->third = Technician::create(['name' => 'Omar']);
});

/**
 * @param  list<int>  $technicianIds
 */
function batchUser(array $technicianIds = []): User
{
    return (new User)->forceFill(['id' => 1, 'technician_ids' => $technicianIds]);
}

/**
 * @return array{data: list<array{id: int, name: string}>}
 */
function renamedTechnicians(Technician ...$technicians): array
{
    return ['data' => array_map(
        fn (Technician $technician) => ['id' => $technician->id, 'name' => $technician->name.' edited'],
        $technicians,
    )];
}

it('updates a batch for a user who may update every record in it', function () {
    $this->actingAs(batchUser([$this->first->id, $this->second->id]))
        ->putJson('/technicians', renamedTechnicians($this->first, $this->second))
        ->assertOk()
        ->assertJsonPath('data', [$this->first->id, $this->second->id]);

    expect($this->first->fresh()->name)->toBe('Ali edited')
        ->and($this->second->fresh()->name)->toBe('Hassan edited');
});

it('forbids a batch update holding one record the user may not update, and changes nothing', function () {
    $this->actingAs(batchUser([$this->first->id]))
        ->putJson('/technicians', renamedTechnicians($this->first, $this->second))
        ->assertForbidden();

    expect($this->first->fresh()->name)->toBe('Ali');
});

it('deletes a batch for a user who may delete every record in it', function () {
    $this->actingAs(batchUser([$this->first->id, $this->second->id]))
        ->deleteJson('/technicians', ['ids' => [$this->first->id, $this->second->id]])
        ->assertOk();

    expect(Technician::query()->pluck('id')->all())->toBe([$this->third->id]);
});

it('forbids a batch delete holding one record the user may not delete, and deletes nothing', function () {
    $this->actingAs(batchUser([$this->first->id]))
        ->deleteJson('/technicians', ['ids' => [$this->first->id, $this->second->id]])
        ->assertForbidden();

    expect(Technician::query()->count())->toBe(3);
});

it('leaves the whole batch to a policy that defines the batch ability', function () {
    Gate::policy(Technician::class, TechnicianWithBatchPolicy::class);

    $this->actingAs(batchUser())
        ->putJson('/technicians', renamedTechnicians($this->first, $this->second))
        ->assertOk();
    $this->actingAs(batchUser([$this->first->id, $this->second->id, $this->third->id]))
        ->putJson('/technicians', renamedTechnicians($this->first, $this->second, $this->third))
        ->assertForbidden();
    $this->actingAs(batchUser([$this->first->id, $this->second->id, $this->third->id]))
        ->deleteJson('/technicians', ['ids' => [$this->first->id, $this->second->id, $this->third->id]])
        ->assertForbidden();
    $this->actingAs(batchUser())
        ->deleteJson('/technicians', ['ids' => [$this->first->id, $this->second->id]])
        ->assertOk();
});

it('leaves the whole batch to an app wide batch ability', function () {
    Gate::define('updateMany', fn (User $user) => true);

    $this->actingAs(batchUser())
        ->putJson('/technicians', renamedTechnicians($this->first, $this->second))
        ->assertOk();
});
