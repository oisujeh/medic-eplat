<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * Create a member of staff holding the given role.
 */
function auditActor(string $roleSlug): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

    return $user->fresh();
}

/**
 * The entries recorded against one record, oldest first.
 */
function entriesFor(object $model, ?string $action = null): Collection
{
    return AuditLog::query()
        ->where('auditable_type', $model::class)
        ->where('auditable_id', $model->getKey())
        ->when($action, fn ($q) => $q->where('action', $action))
        ->orderBy('id')
        ->get();
}

test('creating a record writes a created entry attributed to the signed-in user', function () {
    $officer = auditActor('records-officer');
    actingAs($officer);

    $patient = Patient::factory()->create(['surname' => 'Okafor']);

    $entry = entriesFor($patient, 'created')->sole();

    expect($entry->user_id)->toBe($officer->id)
        ->and($entry->user_name)->toBe($officer->name)
        ->and($entry->patient_id)->toBe($patient->id)
        ->and($entry->old_values)->toBeNull()
        ->and($entry->new_values['surname'])->toBe('Okafor')
        ->and($entry->hash)->toHaveLength(64);
});

test('updating a record stores before and after values for the changed attributes only', function () {
    actingAs(auditActor('records-officer'));

    $patient = Patient::factory()->create(['phone' => '08010000000']);
    $patient->update(['phone' => '08099999999']);

    $entry = entriesFor($patient, 'updated')->sole();

    expect($entry->old_values)->toBe(['phone' => '08010000000'])
        ->and($entry->new_values)->toBe(['phone' => '08099999999']);
});

test('deleting a record keeps its last values in the trail', function () {
    actingAs(auditActor('super-administrator'));

    $patient = Patient::factory()->create(['surname' => 'Bello']);
    $patient->delete();

    $entry = entriesFor($patient, 'deleted')->sole();

    expect($entry->old_values['surname'])->toBe('Bello')
        ->and($entry->new_values)->toBeNull();
});

test('hidden attributes such as passwords never reach the trail', function () {
    $user = User::factory()->create(['password' => 'a-very-secret-password']);

    $entry = entriesFor($user, 'created')->sole();

    expect($entry->new_values)->not->toHaveKey('password')
        ->and($entry->new_values)->not->toHaveKey('remember_token')
        ->and(json_encode($entry->getAttributes()))->not->toContain('a-very-secret-password');
});

test('opening a patient profile is logged as a view of that patient', function () {
    $physician = auditActor('physician');
    $patient = Patient::factory()->create();

    actingAs($physician)
        ->get(route('patients.show', $patient))
        ->assertOk();

    $entry = entriesFor($patient, 'viewed')->sole();

    expect($entry->user_id)->toBe($physician->id)
        ->and($entry->patient_id)->toBe($patient->id)
        ->and($entry->route)->toBe('patients.show');
});

test('partial reloads of a screen already open are not logged as views', function () {
    $patient = Patient::factory()->create();

    actingAs(auditActor('physician'))
        ->withHeaders(['X-Inertia-Partial-Data' => 'patient'])
        ->get(route('patients.show', $patient))
        ->assertOk();

    expect(entriesFor($patient, 'viewed'))->toHaveCount(0);
});

test('a forbidden request does not count as a view', function () {
    $patient = Patient::factory()->create();

    actingAs(auditActor('laboratory-staff'))
        ->get(route('patients.show', $patient))
        ->assertForbidden();

    expect(entriesFor($patient, 'viewed'))->toHaveCount(0);
});

test('audit entries can be neither updated nor deleted', function () {
    $entry = entriesFor(Patient::factory()->create(), 'created')->sole();

    expect(fn () => $entry->forceFill(['action' => 'viewed'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and($entry->fresh()->action)->toBe('created');
});

test('every entry chains to the one before it', function () {
    Patient::factory()->count(3)->create();

    $entries = AuditLog::query()->orderBy('id')->get();

    expect($entries->first()->previous_hash)->toBeNull();

    $entries->sliding(2)->each(function ($pair) {
        [$previous, $current] = $pair->values();

        expect($current->previous_hash)->toBe($previous->hash);
    });
});

test('the verify command passes on an untouched trail', function () {
    Patient::factory()->count(3)->create();

    $this->artisan('audit:verify')
        ->expectsOutputToContain('intact')
        ->assertSuccessful();
});

test('the verify command fails when an entry is edited behind the application', function () {
    Patient::factory()->count(3)->create();
    $target = AuditLog::query()->orderBy('id')->skip(1)->first();

    DB::table('audit_logs')->where('id', $target->id)->update(['action' => 'viewed']);

    $this->artisan('audit:verify')
        ->expectsOutputToContain("broken at entry #{$target->id}")
        ->assertFailed();
});

test('the verify command fails when an entry is removed behind the application', function () {
    Patient::factory()->count(3)->create();
    $target = AuditLog::query()->orderBy('id')->skip(1)->first();

    DB::table('audit_logs')->where('id', $target->id)->delete();

    $this->artisan('audit:verify')->assertFailed();
});

test('sign-in and sign-out are recorded against the account', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));
    event(new Logout('web', $user));

    expect(entriesFor($user, 'login'))->toHaveCount(1)
        ->and(entriesFor($user, 'logout'))->toHaveCount(1);
});

test('failed sign-ins are recorded without the submitted password', function () {
    event(new Failed('web', null, ['login' => 'nobody@example.test', 'password' => 'wrong-secret']));

    $entry = AuditLog::query()->where('action', 'login_failed')->sole();

    expect($entry->label)->toContain('nobody@example.test')
        ->and(json_encode($entry->getAttributes()))->not->toContain('wrong-secret');
});

test('an administrator can browse the trail filtered by patient', function () {
    $admin = auditActor('super-administrator');
    $patient = Patient::factory()->create(['file_number' => 'MRN-0001']);
    Patient::factory()->create(['file_number' => 'MRN-0002']);

    actingAs($admin)
        ->get(route('admin.audit.index', ['patient' => 'MRN-0001']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Audit')
            ->where('entries.total', 1)
            ->where('entries.data.0.patient.id', $patient->id)
            ->where('entries.data.0.action', 'created')
            ->where('filters.patient', 'MRN-0001')
        );
});

test('the trail can be filtered by action and user', function () {
    $admin = auditActor('super-administrator');
    $officer = auditActor('records-officer');

    actingAs($officer);
    Patient::factory()->create();

    actingAs($admin)
        ->get(route('admin.audit.index', ['action' => 'created', 'user' => $officer->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.user_id', $officer->id)
        );
});

test('staff without administration access cannot open the trail', function () {
    actingAs(auditActor('physician'))
        ->get(route('admin.audit.index'))
        ->assertForbidden();
});

test('an administrator can verify the chain from the screen', function () {
    Patient::factory()->create();

    actingAs(auditActor('super-administrator'))
        ->from(route('admin.audit.index'))
        ->post(route('admin.audit.verify'))
        ->assertRedirect(route('admin.audit.index'));
});
