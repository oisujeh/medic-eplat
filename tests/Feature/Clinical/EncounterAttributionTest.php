<?php

use App\Enums\Permission;
use App\Enums\StaffCadre;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function attributionUser(array $roleSlugs, ?StaffCadre $cadre = null): User
{
    $user = User::factory()->create(['cadre' => $cadre]);
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A consultation opened by the given physician.
 *
 * @return array{0: Encounter, 1: User}
 */
function openedConsultation(?User $physician = null): array
{
    $patient = Patient::factory()->create();
    actingAs(attributionUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);
    $entry = QueueEntry::latest('id')->firstOrFail();
    $physician ??= attributionUser(['physician'], StaffCadre::Doctor);

    return [openEncounter($entry, $physician), $physician];
}

test('opening an encounter records who entered it and dates the contact today', function () {
    [$encounter, $physician] = openedConsultation();

    expect($encounter->entered_by)->toBe($physician->id)
        ->and($encounter->entered_at)->not->toBeNull()
        ->and($encounter->encounter_at?->isToday())->toBeTrue()
        ->and($encounter->isBackdated())->toBeFalse();
});

test('signing without a date keeps the contact dated when it was opened', function () {
    [$encounter, $physician] = openedConsultation();

    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria'])
        ->assertSessionHasNoErrors();

    $encounter->refresh();

    expect($encounter->author_id)->toBe($physician->id)
        ->and($encounter->entered_by)->toBe($physician->id)
        ->and($encounter->clinicalDate()?->isToday())->toBeTrue();
});

test('a physician cannot backdate a note without the permission', function () {
    [$encounter, $physician] = openedConsultation();

    expect($physician->hasPermission(Permission::BackdateEncounters))->toBeFalse();

    actingAs($physician)->post(route('encounters.sign', $encounter), [
        'assessment' => 'Malaria',
        'encounter_at' => now()->subDays(3)->toDateString(),
    ])->assertSessionHasErrors('encounter_at');

    expect($encounter->fresh()->isSigned())->toBeFalse();
});

test('a records officer with the permission enters a paper note on behalf of the clinician', function () {
    $clinician = attributionUser(['physician'], StaffCadre::Doctor);
    $clerk = attributionUser(['records-officer', 'physician']);
    [$encounter] = openedConsultation($clerk);

    expect($clerk->hasPermission(Permission::BackdateEncounters))->toBeTrue();

    actingAs($clerk)->post(route('encounters.sign', $encounter), [
        'assessment' => 'Malaria',
        'provider_id' => $clinician->id,
        'encounter_at' => now()->subDays(3)->toDateString(),
    ])->assertSessionHasNoErrors();

    $encounter->refresh();

    expect($encounter->author_id)->toBe($clinician->id)
        ->and($encounter->entered_by)->toBe($clerk->id)
        ->and($encounter->encounter_at?->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($encounter->isBackdated())->toBeTrue();
});

test('only a clinical cadre can be named as the provider', function () {
    $cashier = attributionUser(['cashier'], StaffCadre::Cashier);
    [$encounter, $physician] = openedConsultation();

    actingAs($physician)->post(route('encounters.sign', $encounter), [
        'assessment' => 'Malaria',
        'provider_id' => $cashier->id,
    ])->assertSessionHasErrors('provider_id');
});

test('an encounter date in the future is refused', function () {
    [$encounter, $physician] = openedConsultation();

    actingAs($physician)->post(route('encounters.sign', $encounter), [
        'assessment' => 'Malaria',
        'encounter_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('encounter_at');
});
