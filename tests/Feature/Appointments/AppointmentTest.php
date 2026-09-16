<?php

use App\Enums\AppointmentStatus;
use App\Enums\QueueStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ProviderSchedule;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function apptUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function consultationPoint(): ServicePoint
{
    return ServicePoint::where('slug', 'consultation')->firstOrFail();
}

/** Next occurrence of a weekday (0=Sun..6=Sat) at the given time. */
function nextWeekdayAt(int $weekday, int $hour, int $minute = 0): Carbon
{
    $carbonDay = [Carbon::SUNDAY, Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY][$weekday];

    return Carbon::now()->next($carbonDay)->setTime($hour, $minute, 0);
}

test('a records officer can open the appointments calendar', function () {
    actingAs(apptUser(['records-officer']))
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('appointments/Index'));
});

test('staff without the appointments module are forbidden', function () {
    actingAs(apptUser(['laboratory-staff']))
        ->get(route('appointments.index'))
        ->assertForbidden();
});

test('a patient can be booked into an available slot', function () {
    $records = apptUser(['records-officer']);
    $provider = apptUser(['physician']);
    $patient = Patient::factory()->create();
    $sp = consultationPoint();
    ProviderSchedule::factory()->create(['provider_id' => $provider->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 30]);

    $start = nextWeekdayAt(1, 9);

    actingAs($records)->post(route('appointments.store'), [
        'patient_id' => $patient->id,
        'service_point_id' => $sp->id,
        'provider_id' => $provider->id,
        'scheduled_start' => $start->toDateTimeString(),
        'duration_minutes' => 30,
        'priority' => 'normal',
        'reason' => 'Follow-up',
    ])->assertRedirect();

    $appt = Appointment::first();
    expect($appt)->not->toBeNull();
    expect($appt->status)->toBe(AppointmentStatus::Scheduled);
    expect($appt->provider_id)->toBe($provider->id);
    expect($appt->scheduled_end->equalTo($start->copy()->addMinutes(30)))->toBeTrue();
});

test('double-booking a provider is rejected', function () {
    $records = apptUser(['records-officer']);
    $provider = apptUser(['physician']);
    $sp = consultationPoint();
    ProviderSchedule::factory()->create(['provider_id' => $provider->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 30]);
    $start = nextWeekdayAt(1, 9);

    Appointment::factory()->at($start, 30)->create([
        'provider_id' => $provider->id,
        'service_point_id' => $sp->id,
    ]);

    actingAs($records)->post(route('appointments.store'), [
        'patient_id' => Patient::factory()->create()->id,
        'service_point_id' => $sp->id,
        'provider_id' => $provider->id,
        'scheduled_start' => $start->toDateTimeString(),
        'duration_minutes' => 30,
    ])->assertSessionHasErrors('scheduled_start');
});

test('booking outside the provider availability is rejected', function () {
    $records = apptUser(['records-officer']);
    $provider = apptUser(['physician']);
    $sp = consultationPoint();
    ProviderSchedule::factory()->create(['provider_id' => $provider->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 30]);

    // 14:00 on the same weekday is outside the 09:00–12:00 template.
    actingAs($records)->post(route('appointments.store'), [
        'patient_id' => Patient::factory()->create()->id,
        'service_point_id' => $sp->id,
        'provider_id' => $provider->id,
        'scheduled_start' => nextWeekdayAt(1, 14)->toDateTimeString(),
        'duration_minutes' => 30,
    ])->assertSessionHasErrors('scheduled_start');
});

test('the slots endpoint reflects the schedule minus existing bookings', function () {
    $records = apptUser(['records-officer']);
    $provider = apptUser(['physician']);
    $sp = consultationPoint();
    ProviderSchedule::factory()->create(['provider_id' => $provider->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '11:00', 'slot_minutes' => 30]);
    $booked = nextWeekdayAt(1, 9, 30);
    Appointment::factory()->at($booked, 30)->create(['provider_id' => $provider->id, 'service_point_id' => $sp->id]);

    $response = actingAs($records)->getJson(route('appointments.slots', [
        'provider_id' => $provider->id,
        'service_point_id' => $sp->id,
        'date' => $booked->toDateString(),
    ]));

    $response->assertOk();
    $slots = collect($response->json('slots'));
    expect($slots)->toHaveCount(4); // 09:00, 09:30, 10:00, 10:30
    expect($slots->firstWhere('label', '09:30 AM')['available'])->toBeFalse();
    expect($slots->firstWhere('label', '10:00 AM')['available'])->toBeTrue();
});

test('an appointment can be rescheduled', function () {
    $records = apptUser(['records-officer']);
    $provider = apptUser(['physician']);
    $sp = consultationPoint();
    ProviderSchedule::factory()->create(['provider_id' => $provider->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 30]);
    $appt = Appointment::factory()->at(nextWeekdayAt(1, 9), 30)->create(['provider_id' => $provider->id, 'service_point_id' => $sp->id]);

    $newStart = nextWeekdayAt(1, 10, 30);
    actingAs($records)->patch(route('appointments.update', $appt), [
        'scheduled_start' => $newStart->toDateTimeString(),
    ])->assertRedirect();

    expect($appt->fresh()->scheduled_start->equalTo($newStart))->toBeTrue();
});

test('an appointment can be cancelled and marked no-show', function () {
    $records = apptUser(['records-officer']);
    $sp = consultationPoint();
    $appt = Appointment::factory()->create(['service_point_id' => $sp->id, 'provider_id' => apptUser(['physician'])->id]);

    actingAs($records)->post(route('appointments.cancel', $appt), ['reason' => 'Patient called'])->assertRedirect();
    expect($appt->fresh()->status)->toBe(AppointmentStatus::Cancelled);

    $appt2 = Appointment::factory()->create(['service_point_id' => $sp->id]);
    actingAs($records)->post(route('appointments.no-show', $appt2))->assertRedirect();
    expect($appt2->fresh()->status)->toBe(AppointmentStatus::NoShow);
});

test('checking in an appointment routes the patient into the queue', function () {
    $records = apptUser(['records-officer']);
    $sp = consultationPoint();
    $appt = Appointment::factory()->create(['service_point_id' => $sp->id, 'provider_id' => apptUser(['physician'])->id]);

    actingAs($records)->post(route('appointments.check-in', $appt))->assertRedirect();

    $appt->refresh();
    expect($appt->status)->toBe(AppointmentStatus::CheckedIn);
    expect($appt->queue_entry_id)->not->toBeNull();
    expect($appt->visit_id)->not->toBeNull();

    $entry = QueueEntry::find($appt->queue_entry_id);
    expect($entry->service_point_id)->toBe($sp->id);
    expect($entry->status)->toBe(QueueStatus::Waiting);
});

test('a walk-in creates a checked-in appointment and a queue entry', function () {
    $records = apptUser(['records-officer']);
    $patient = Patient::factory()->create();
    $sp = consultationPoint();

    actingAs($records)->post(route('appointments.walk-in'), [
        'patient_id' => $patient->id,
        'service_point_id' => $sp->id,
        'priority' => 'normal',
        'reason' => 'Walk-in complaint',
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $patient->id)->first();
    expect($appt->source->value)->toBe('walk_in');
    expect($appt->status)->toBe(AppointmentStatus::CheckedIn);
    expect(QueueEntry::where('patient_id', $patient->id)->where('service_point_id', $sp->id)->exists())->toBeTrue();
});

test('the calendar can be pre-filled from a patient profile', function () {
    $records = apptUser(['records-officer']);
    $patient = Patient::factory()->create();

    actingAs($records)->get(route('appointments.index', ['patient_id' => $patient->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('prefill.id', $patient->id)
            ->where('prefill.name', $patient->fullName()));
});

test('a provider weekly schedule can be created', function () {
    $records = apptUser(['records-officer']);
    $provider = apptUser(['physician']);

    actingAs($records)->post(route('appointments.schedules.store'), [
        'provider_id' => $provider->id,
        'weekday' => 2,
        'start_time' => '08:00',
        'end_time' => '13:00',
        'slot_minutes' => 20,
    ])->assertRedirect();

    expect(ProviderSchedule::where('provider_id', $provider->id)->where('weekday', 2)->exists())->toBeTrue();
});
