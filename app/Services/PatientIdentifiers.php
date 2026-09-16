<?php

namespace App\Services;

use App\Enums\IdentifierType;
use App\Models\Patient;
use App\Models\PatientIdentifier;
use App\Support\MatchResult;
use Illuminate\Support\Collection;

/**
 * The Master Patient Index: keeps the identifier index in step with the
 * patient record and finds the people a set of details could already belong
 * to, so a returning patient is not registered twice.
 */
class PatientIdentifiers
{
    private const MAX_CANDIDATES = 8;

    public function __construct(private readonly PatientMatcher $matcher) {}

    /**
     * Mirror the identifiers held on the patient record (file number, NIN,
     * phone, HMO number) into the index, adding, replacing and removing rows
     * as the columns change. Identifiers held only in the index are untouched.
     */
    public function sync(Patient $patient): void
    {
        foreach (IdentifierType::cases() as $type) {
            $attribute = $type->patientAttribute();

            if ($attribute === null) {
                continue;
            }

            $raw = $patient->getAttribute($attribute);
            $value = is_string($raw) && trim($raw) !== '' ? $type->normalise($raw) : null;

            $existing = $patient->identifiers()->where('type', $type->value)->get();

            foreach ($existing as $row) {
                if ($value === null || $row->value !== $value) {
                    $row->delete();
                }
            }

            if ($value !== null && ! $existing->contains('value', $value)) {
                $patient->identifiers()->create(['type' => $type->value, 'value' => $value]);
            }
        }
    }

    /**
     * The patient holding an identifier that can belong to only one person.
     */
    public function holderOf(IdentifierType $type, string $value): ?Patient
    {
        return PatientIdentifier::query()
            ->where('type', $type->value)
            ->where('value', $type->normalise($value))
            ->whereHas('patient', fn ($q) => $q->whereNull('merged_into_id'))
            ->with('patient')
            ->first()
            ?->patient;
    }

    /**
     * People the given details may already describe, best match first,
     * scored by the matching engine.
     *
     * @param  array<string, mixed>  $details
     * @return Collection<int, MatchResult>
     */
    public function candidates(array $details, ?Patient $except = null): Collection
    {
        return $this->matcher->candidatesFor($details, $except)->take(self::MAX_CANDIDATES)->values();
    }
}
