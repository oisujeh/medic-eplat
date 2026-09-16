<?php

use App\Enums\ClaimBatchStatus;
use App\Enums\ClaimStatus;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillCharge;
use App\Models\Claim;
use App\Models\ClaimBatch;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function claimsUser(array $roleSlugs = ['cashier']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * An enrollee of the given payer.
 */
function enrolleeOf(Payer $payer): Patient
{
    return Patient::factory()->create([
        'coverage' => 'hmo',
        'payer_id' => $payer->id,
        'hmo_name' => $payer->name,
        'hmo_number' => 'ENR-0001',
        'hmo_plan' => 'Formal sector',
    ]);
}

/**
 * A bill carrying a consultation (2,000), drugs (1,000) and a lab test (1,500).
 */
function billedVisit(Patient $patient): Bill
{
    $billing = app(BillingService::class);
    $actor = claimsUser(['physician']);
    $bill = $billing->openBillFor($patient, null);

    $billing->postCharge($bill, BillCharge::SOURCE_CONSULTATION, 'Consultation — GOPD', 1, 2000, $actor);
    $billing->postCharge($bill, BillCharge::SOURCE_PHARMACY, 'Artemether/Lumefantrine × 1', 1, 1000, $actor);
    $billing->postCharge($bill, BillCharge::SOURCE_LABORATORY, 'Malaria Parasite', 1, 1500, $actor);

    return $bill->fresh();
}

test('a cashier can open the claims desk', function () {
    actingAs(claimsUser())
        ->get(route('claims.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('claims/Index'));
});

test('staff without the claims module are forbidden', function () {
    actingAs(claimsUser(['physician']))
        ->get(route('claims.index'))
        ->assertForbidden();
});

test('raising a claim splits the bill between the payer and the enrollee', function () {
    $nhia = Payer::factory()->nhia()->create();
    $bill = billedVisit(enrolleeOf($nhia));

    actingAs(claimsUser())
        ->post(route('claims.store'), ['bill_id' => $bill->id])
        ->assertRedirect();

    $claim = Claim::firstOrFail();

    expect($claim->status)->toBe(ClaimStatus::Draft)
        ->and($claim->claim_number)->toStartWith('CLM/')
        ->and($claim->enrollee_number)->toBe('ENR-0001')
        ->and($claim->lines()->count())->toBe(3)
        ->and($claim->gross_amount)->toBe(4500.0)
        ->and($claim->discount_amount)->toBe(0.0)
        ->and($claim->copay_amount)->toBe(100.0)
        ->and($claim->payer_amount)->toBe(4400.0);

    $drugLine = $claim->lines()->where('source', BillCharge::SOURCE_PHARMACY)->firstOrFail();

    expect($drugLine->copay_amount)->toBe(100.0)
        ->and($drugLine->payer_amount)->toBe(900.0);

    // The bill now only expects the enrollee's 10% on drugs.
    $bill->refresh();
    $hmoPayment = Payment::where('method', PaymentMethod::Hmo->value)->firstOrFail();

    expect($hmoPayment->amount)->toBe(4400.0)
        ->and($hmoPayment->reference)->toBe($claim->claim_number)
        ->and(Payment::where('method', PaymentMethod::Waiver->value)->count())->toBe(0)
        ->and($bill->balance())->toBe(100.0);
});

test('a payer discount is written off as a tariff waiver', function () {
    $hmo = Payer::factory()->create(['discount_percent' => 20, 'drug_copay_percent' => 10]);
    $bill = billedVisit(enrolleeOf($hmo));

    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);

    $claim = Claim::firstOrFail();

    expect($claim->gross_amount)->toBe(4500.0)
        ->and($claim->discount_amount)->toBe(900.0)
        ->and($claim->copay_amount)->toBe(80.0)
        ->and($claim->payer_amount)->toBe(3520.0)
        ->and(Payment::where('method', PaymentMethod::Waiver->value)->value('amount'))->toBe(900.0)
        ->and($bill->fresh()->balance())->toBe(80.0);
});

test('only HMO patients with a payer on record can be claimed for', function () {
    $private = Patient::factory()->create(['coverage' => 'private']);
    $bill = billedVisit($private);

    actingAs(claimsUser())
        ->from(route('claims.index'))
        ->post(route('claims.store'), ['bill_id' => $bill->id])
        ->assertRedirect(route('claims.index', absolute: false))
        ->assertSessionHasErrors('bill_id');

    expect(Claim::count())->toBe(0);
});

test('charges already on a claim cannot be claimed again', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));

    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);

    actingAs(claimsUser())
        ->post(route('claims.store'), ['bill_id' => $bill->id])
        ->assertSessionHasErrors('bill_id');

    expect(Claim::count())->toBe(1);
});

test('a claim can be raised for a subset of charges and the rest later', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    $consult = $bill->charges()->where('source', BillCharge::SOURCE_CONSULTATION)->firstOrFail();

    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id, 'charge_ids' => [$consult->id]]);

    expect(Claim::firstOrFail()->lines()->count())->toBe(1);

    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id])->assertSessionHasNoErrors();

    expect(Claim::count())->toBe(2)
        ->and(Claim::latest('id')->firstOrFail()->lines()->count())->toBe(2);
});

test('marking a line as not covered moves it back to the enrollee', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();
    $lab = $claim->lines()->where('source', BillCharge::SOURCE_LABORATORY)->firstOrFail();

    actingAs(claimsUser())
        ->patch(route('claims.lines.update', [$claim, $lab]), ['is_covered' => false, 'remark' => 'Not on NHIA benefit package'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $lab->refresh();
    $claim->refresh();

    expect($lab->is_covered)->toBeFalse()
        ->and($lab->copay_amount)->toBe(1500.0)
        ->and($lab->payer_amount)->toBe(0.0)
        ->and($claim->payer_amount)->toBe(2900.0)
        ->and($claim->copay_amount)->toBe(1600.0)
        ->and(Payment::find($claim->hmo_payment_id)->amount)->toBe(2900.0)
        ->and($bill->fresh()->balance())->toBe(1600.0);
});

test('a line tariff can be revised while the claim is a draft', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();
    $consult = $claim->lines()->where('source', BillCharge::SOURCE_CONSULTATION)->firstOrFail();

    actingAs(claimsUser())
        ->patch(route('claims.lines.update', [$claim, $consult]), ['amount' => 1500, 'copay_amount' => 0])
        ->assertSessionHasNoErrors();

    expect($claim->fresh()->payer_amount)->toBe(3900.0)
        ->and($claim->fresh()->discount_amount)->toBe(500.0);

    actingAs(claimsUser())
        ->patch(route('claims.lines.update', [$claim, $consult]), ['amount' => 1000, 'copay_amount' => 1200])
        ->assertSessionHasErrors('copay_amount');
});

test('an authorisation code is recorded on the claim', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();

    actingAs(claimsUser())
        ->post(route('claims.authorize', $claim), ['authorization_code' => 'AUTH-77812', 'authorized_at' => '2026-09-02', 'authorization_note' => 'Approved by call centre'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($claim->fresh()->authorization_code)->toBe('AUTH-77812')
        ->and($claim->fresh()->authorized_at->toDateString())->toBe('2026-09-02');
});

test('submitting a claim places it in this month\'s schedule for the payer', function () {
    Carbon::setTestNow('2026-09-04 10:00:00');
    $nhia = Payer::factory()->nhia()->create();
    $bill = billedVisit(enrolleeOf($nhia));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();

    actingAs(claimsUser())
        ->post(route('claims.submit', $claim))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $claim->refresh();
    $batch = ClaimBatch::firstOrFail();

    expect($claim->status)->toBe(ClaimStatus::Submitted)
        ->and($claim->submitted_at)->not->toBeNull()
        ->and($claim->claim_batch_id)->toBe($batch->id)
        ->and($batch->batch_number)->toBe('NHIA/2026-09')
        ->and($batch->period)->toBe('2026-09')
        ->and($batch->status)->toBe(ClaimBatchStatus::Open);

    // A second claim for the same payer this month joins the same schedule.
    $bill2 = billedVisit(enrolleeOf($nhia));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill2->id]);
    actingAs(claimsUser())->post(route('claims.submit', Claim::latest('id')->firstOrFail()));

    expect(ClaimBatch::count())->toBe(1)
        ->and($batch->claims()->count())->toBe(2);

    // Lines are frozen once submitted.
    actingAs(claimsUser())
        ->patch(route('claims.lines.update', [$claim, $claim->lines()->first()]), ['amount' => 1])
        ->assertSessionHasErrors('status');

    Carbon::setTestNow();
});

test('remittances are recorded until the approved amount is paid', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();
    actingAs(claimsUser())->post(route('claims.submit', $claim));

    actingAs(claimsUser())
        ->post(route('claims.remit', $claim), ['approved_amount' => 4400, 'paid_amount' => 2000, 'reference' => 'RMT-1'])
        ->assertSessionHasNoErrors();

    $claim->refresh();

    expect($claim->status)->toBe(ClaimStatus::PartiallyPaid)
        ->and($claim->paid_amount)->toBe(2000.0)
        ->and($claim->outstandingAmount())->toBe(2400.0);

    actingAs(claimsUser())
        ->post(route('claims.remit', $claim), ['approved_amount' => 4400, 'paid_amount' => 2400, 'reference' => 'RMT-2'])
        ->assertSessionHasNoErrors();

    $claim->refresh();

    expect($claim->status)->toBe(ClaimStatus::Paid)
        ->and($claim->paid_amount)->toBe(4400.0)
        ->and($claim->outstandingAmount())->toBe(0.0)
        ->and($claim->remittance_reference)->toBe('RMT-2');
});

test('a payer deduction shows as a shortfall once paid', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();
    actingAs(claimsUser())->post(route('claims.submit', $claim));

    actingAs(claimsUser())
        ->post(route('claims.remit', $claim), ['approved_amount' => 4000, 'paid_amount' => 4000])
        ->assertSessionHasNoErrors();

    $claim->refresh();

    expect($claim->status)->toBe(ClaimStatus::Paid)
        ->and($claim->shortfallAmount())->toBe(400.0);
});

test('a submitted claim can be rejected, and a draft cannot', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();

    actingAs(claimsUser())
        ->post(route('claims.reject', $claim), ['reason' => 'Enrollee not found'])
        ->assertSessionHasErrors('status');

    actingAs(claimsUser())->post(route('claims.submit', $claim));

    actingAs(claimsUser())
        ->post(route('claims.reject', $claim), ['reason' => 'Enrollee not found'])
        ->assertSessionHasNoErrors();

    expect($claim->fresh()->status)->toBe(ClaimStatus::Rejected)
        ->and($claim->fresh()->rejection_reason)->toBe('Enrollee not found');
});

test('discarding a draft restores what the enrollee owes', function () {
    $hmo = Payer::factory()->create(['discount_percent' => 20, 'drug_copay_percent' => 10]);
    $bill = billedVisit(enrolleeOf($hmo));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();

    expect($bill->fresh()->balance())->toBe(80.0);

    actingAs(claimsUser())
        ->delete(route('claims.destroy', $claim))
        ->assertRedirect(route('claims.index', absolute: false));

    expect(Claim::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($bill->fresh()->balance())->toBe(4500.0);

    // The charges are claimable again.
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id])->assertSessionHasNoErrors();
});

test('a schedule is submitted to the payer with its claims', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    actingAs(claimsUser())->post(route('claims.submit', Claim::firstOrFail()));
    $batch = ClaimBatch::firstOrFail();

    actingAs(claimsUser())
        ->get(route('claims.batches.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('claims/Batch')
            ->where('batch.claims_count', 1)
            ->where('batch.payer_amount', 4400)
            ->has('claims', 1)
        );

    actingAs(claimsUser())
        ->post(route('claims.batches.submit', $batch), ['reference' => 'NHIA-SEP-2026'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $batch->refresh();

    expect($batch->status)->toBe(ClaimBatchStatus::Submitted)
        ->and($batch->reference)->toBe('NHIA-SEP-2026')
        ->and($batch->submitted_at)->not->toBeNull();

    actingAs(claimsUser())
        ->post(route('claims.batches.submit', $batch))
        ->assertSessionHasErrors('status');
});

test('the claim page and register render', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    $claim = Claim::firstOrFail();

    actingAs(claimsUser())
        ->get(route('claims.show', $claim))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('claims/Show')
            ->where('claim.payer_amount', 4400)
            ->where('claim.is_draft', true)
            ->has('lines', 3)
        );

    actingAs(claimsUser())
        ->get(route('claims.index', ['status' => 'draft']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('claims.data', 1)
            ->where('stats.draft_count', 1)
        );
});

test('the bill page offers a claim for HMO patients with unclaimed charges', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));

    actingAs(claimsUser())
        ->get(route('billing.show', $bill))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canClaim', true)->has('claims', 0));

    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);

    actingAs(claimsUser())
        ->get(route('billing.show', $bill))
        ->assertInertia(fn ($page) => $page->where('canClaim', false)->has('claims', 1));
});

test('registering an HMO patient against a payer sets the provider name', function () {
    $nhia = Payer::factory()->nhia()->create();

    actingAs(claimsUser(['records-officer']))
        ->post(route('patients.store'), [
            'title' => 'Mr',
            'surname' => 'Bello',
            'first_name' => 'Musa',
            'date_of_birth' => '1985-01-01',
            'sex' => 'M',
            'marital_status' => 'Married',
            'phone' => '08030000000',
            'nationality' => 'Nigerian',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
            'coverage' => 'hmo',
            'payer_id' => $nhia->id,
            'hmo_number' => 'NHIA-123',
            'hmo_plan' => 'Formal sector',
            'hmo_expires_at' => '2027-01-31',
            'is_transfer' => false,
            'visit_category' => 'Outpatient',
            'outpatient_service' => 'Clinical Consultation & Diagnosis',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $patient = Patient::firstOrFail();

    expect($patient->payer_id)->toBe($nhia->id)
        ->and($patient->hmo_name)->toBe($nhia->name)
        ->and($patient->hmo_plan)->toBe('Formal sector')
        ->and($patient->hmo_expires_at->toDateString())->toBe('2027-01-31');
});

test('the insurance reports run', function () {
    $bill = billedVisit(enrolleeOf(Payer::factory()->nhia()->create()));
    actingAs(claimsUser())->post(route('claims.store'), ['bill_id' => $bill->id]);
    actingAs(claimsUser())->post(route('claims.submit', Claim::firstOrFail()));
    $cmd = claimsUser(['chief-medical-director']);

    actingAs($cmd)->get(route('reports.run', 'claims-register'))->assertOk();
    actingAs($cmd)->get(route('reports.run', 'claims-outstanding'))->assertOk();
});
