<?php

namespace App\Support\DataQuality;

use App\Models\PatientIdentifier;
use Generator;

/**
 * Finding identifiers that appear on more than one live folder. Merged
 * folders keep their identifiers on purpose, so they are left out.
 */
final class SharedIdentifiers
{
    /**
     * Every identifier whose type and value are recorded against more than
     * one unmerged patient.
     *
     * @return Generator<int, PatientIdentifier>
     */
    public static function shared(): Generator
    {
        $pairs = PatientIdentifier::query()
            ->join('patients', 'patients.id', '=', 'patient_identifiers.patient_id')
            ->whereNull('patients.merged_into_id')
            ->select('patient_identifiers.type', 'patient_identifiers.value')
            ->groupBy('patient_identifiers.type', 'patient_identifiers.value')
            ->havingRaw('count(distinct patient_identifiers.patient_id) > 1')
            ->get();

        foreach ($pairs as $pair) {
            $identifiers = PatientIdentifier::query()
                ->where('type', $pair->type)
                ->where('value', $pair->value)
                ->whereHas('patient', fn ($query) => $query->whereNull('merged_into_id'))
                ->get();

            foreach ($identifiers as $identifier) {
                yield $identifier;
            }
        }
    }
}
