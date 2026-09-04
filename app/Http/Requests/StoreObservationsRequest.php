<?php

namespace App\Http\Requests;

use App\Enums\AdmissionStatus;
use App\Enums\ObservationCode;
use App\Models\Admission;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\QueueEntry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record a set of measurements for a patient. The context — a queue entry, an
 * encounter or an admission — decides who may record and what the set links
 * to; without one, any clinical staff member may record.
 */
class StoreObservationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($entry = $this->queueEntry()) {
            $entry->loadMissing('servicePoint');

            return $entry->patient_id === $this->patient()->id
                && $entry->status->isActive()
                && $user->canAccessModule($entry->servicePoint->module_slug ?? '');
        }

        if ($encounter = $this->encounter()) {
            return $encounter->patient_id === $this->patient()->id && $user->can('document', $encounter);
        }

        if ($admission = $this->admission()) {
            return $admission->patient_id === $this->patient()->id
                && $admission->status === AdmissionStatus::Admitted
                && $user->canAccessModule('admissions');
        }

        return collect(['nursing', 'clinical', 'admissions'])->contains(fn (string $m) => $user->canAccessModule($m));
    }

    /**
     * Get the validation rules that apply to the request. Bounds come from
     * the code itself and are sanity checks, not clinical thresholds.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'queue_entry_id' => ['nullable', Rule::exists('queue_entries', 'id')],
            'encounter_id' => ['nullable', Rule::exists('encounters', 'id')],
            'admission_id' => ['nullable', Rule::exists('admissions', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        foreach (ObservationCode::enterable() as $code) {
            if ($code->isText()) {
                $rules[$code->value] = ['nullable', 'string', 'max:100'];

                continue;
            }

            [$min, $max] = $code->bounds() ?? [0, PHP_FLOAT_MAX];
            $rules[$code->value] = ['nullable', 'numeric', "between:{$min},{$max}"];
        }

        return $rules;
    }

    /**
     * Require that at least one measurement was entered.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasMeasurement = collect(ObservationCode::enterable())
                ->contains(fn (ObservationCode $code) => $this->filled($code->value));

            if (! $hasMeasurement) {
                $validator->errors()->add(ObservationCode::Temperature->value, 'Record at least one measurement.');
            }
        });
    }

    /**
     * The readings that were entered, keyed by code.
     *
     * @return array<string, mixed>
     */
    public function readings(): array
    {
        return collect($this->validated())
            ->only(array_map(fn (ObservationCode $c) => $c->value, ObservationCode::enterable()))
            ->all();
    }

    public function patient(): Patient
    {
        /** @var Patient */
        return $this->route('patient');
    }

    public function queueEntry(): ?QueueEntry
    {
        return $this->filled('queue_entry_id') ? QueueEntry::find($this->integer('queue_entry_id')) : null;
    }

    public function encounter(): ?Encounter
    {
        return $this->filled('encounter_id') ? Encounter::find($this->integer('encounter_id')) : null;
    }

    public function admission(): ?Admission
    {
        return $this->filled('admission_id') ? Admission::find($this->integer('admission_id')) : null;
    }
}
