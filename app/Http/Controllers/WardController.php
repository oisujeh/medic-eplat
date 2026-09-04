<?php

namespace App\Http\Controllers;

use App\Enums\BedStatus;
use App\Enums\ServiceCategory;
use App\Enums\WardType;
use App\Http\Requests\StoreBedsRequest;
use App\Http\Requests\StoreWardRequest;
use App\Http\Requests\UpdateBedRequest;
use App\Http\Requests\UpdateWardRequest;
use App\Models\Bed;
use App\Models\ServiceCharge;
use App\Models\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ward set-up and the bed board.
 */
class WardController extends Controller
{
    /**
     * A ward's bed board: every bed, who is in it, and how long they have
     * been there.
     */
    public function show(Ward $ward): Response
    {
        $ward->load([
            'bedCharge:id,name,price',
            'beds.currentAdmission.patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
            'beds.currentAdmission.attending:id,name',
        ]);

        $occupancy = $ward->occupancy();

        return Inertia::render('admissions/Ward', [
            'ward' => [
                'id' => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
                'type' => $ward->type->value,
                'type_label' => $ward->type->label(),
                'description' => $ward->description,
                'is_active' => $ward->is_active,
                'bed_service_charge_id' => $ward->bed_service_charge_id,
                'bed_charge' => $ward->bedCharge ? [
                    'name' => $ward->bedCharge->name,
                    'price' => (float) $ward->bedCharge->price,
                ] : null,
                ...$occupancy,
            ],
            'beds' => $ward->beds->map(fn (Bed $bed) => [
                'id' => $bed->id,
                'label' => $bed->label,
                'status' => $bed->status->value,
                'status_label' => $bed->status->label(),
                'tone' => $bed->status->tone(),
                'notes' => $bed->notes,
                'occupant' => $bed->currentAdmission ? [
                    'name' => $bed->currentAdmission->patient->fullName(),
                    'initials' => $bed->currentAdmission->patient->initials(),
                    'file_number' => $bed->currentAdmission->patient->file_number,
                    'sex' => $bed->currentAdmission->patient->sex,
                    'age' => $bed->currentAdmission->patient->age(),
                    'attending' => $bed->currentAdmission->attending?->name,
                    'admitted_diff' => $bed->currentAdmission->admitted_at?->diffForHumans(),
                    'days' => $bed->currentAdmission->lengthOfStayDays(),
                    'url' => route('admissions.show', $bed->currentAdmission),
                ] : null,
            ]),
            'wardTypes' => WardType::options(),
            'bedCharges' => $this->bedCharges(),
        ]);
    }

    /**
     * Create a ward, optionally with its first beds.
     */
    public function store(StoreWardRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ward = Ward::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'],
            'bed_service_charge_id' => $data['bed_service_charge_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'sort_order' => ((int) Ward::max('sort_order')) + 10,
        ]);

        $beds = (int) ($data['initial_beds'] ?? 0);

        if ($beds > 0) {
            $ward->addBeds($beds, ($data['bed_prefix'] ?? null) ?: 'Bed');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ward->name} created."]);

        return to_route('admissions.wards.show', $ward);
    }

    /**
     * Update a ward's details.
     */
    public function update(UpdateWardRequest $request, Ward $ward): RedirectResponse
    {
        $ward->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ward saved.']);

        return back();
    }

    /**
     * Add beds to a ward, continuing its numbering.
     */
    public function storeBeds(StoreBedsRequest $request, Ward $ward): RedirectResponse
    {
        $created = $ward->addBeds($request->integer('count'), $request->input('prefix') ?: 'Bed');

        Inertia::flash('toast', ['type' => 'success', 'message' => $created->count().' '.($created->count() === 1 ? 'bed' : 'beds').' added.']);

        return back();
    }

    /**
     * Take a bed out of service, or bring it back, and relabel it.
     */
    public function updateBed(UpdateBedRequest $request, Bed $bed): RedirectResponse
    {
        if ($bed->status === BedStatus::Occupied) {
            throw ValidationException::withMessages(['status' => 'An occupied bed cannot be changed. Discharge or transfer the patient first.']);
        }

        $bed->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$bed->label} updated."]);

        return back();
    }

    /**
     * Fee schedule entries that can price a bed.
     *
     * @return Collection<int, array{id: int, name: string, price: float}>
     */
    public static function bedCharges(): Collection
    {
        return ServiceCharge::query()
            ->where('category', ServiceCategory::Bed->value)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceCharge $charge) => [
                'id' => $charge->id,
                'name' => $charge->name,
                'price' => (float) $charge->price,
            ]);
    }
}
