<?php

namespace App\Enums;

/**
 * What kind of thing a concept names.
 */
enum ConceptClass: string
{
    case Diagnosis = 'diagnosis';
    case Observation = 'observation';
    case LabTest = 'lab_test';
    case Drug = 'drug';
    case Allergen = 'allergen';
    case Vaccine = 'vaccine';
    case Procedure = 'procedure';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Diagnosis => 'Diagnosis',
            self::Observation => 'Observation',
            self::LabTest => 'Laboratory test',
            self::Drug => 'Drug',
            self::Allergen => 'Allergen',
            self::Vaccine => 'Vaccine',
            self::Procedure => 'Procedure',
            self::Other => 'Other',
        };
    }
}
