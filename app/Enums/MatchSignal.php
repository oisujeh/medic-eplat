<?php

namespace App\Enums;

/**
 * The signals the deduplication engine compares two records on. Each adds
 * its weight to the match score when it fires; the weights are editable in
 * `matching_rules` and fall back to the defaults here.
 */
enum MatchSignal: string
{
    case NinExact = 'nin_exact';
    case PhoneExact = 'phone_exact';
    case DobExact = 'dob_exact';
    case DobYear = 'dob_year';
    case SexSame = 'sex_same';
    case SexDiffers = 'sex_differs';
    case SurnameExact = 'surname_exact';
    case SurnameSounds = 'surname_sounds';
    case FirstNameExact = 'first_name_exact';
    case FirstNameSounds = 'first_name_sounds';
    case NamesSwapped = 'names_swapped';
    case LgaSame = 'lga_same';
    case ParentSame = 'parent_same';
    case MotherLinked = 'mother_linked';

    public function label(): string
    {
        return match ($this) {
            self::NinExact => 'Same NIN',
            self::PhoneExact => 'Same phone number',
            self::DobExact => 'Same date of birth',
            self::DobYear => 'Born the same year',
            self::SexSame => 'Same sex',
            self::SexDiffers => 'Different sex',
            self::SurnameExact => 'Same surname',
            self::SurnameSounds => 'Surname sounds alike',
            self::FirstNameExact => 'Same first name',
            self::FirstNameSounds => 'First name sounds alike',
            self::NamesSwapped => 'Surname and first name swapped',
            self::LgaSame => 'Same LGA',
            self::ParentSame => 'Same parent named',
            self::MotherLinked => 'Same mother on record',
        };
    }

    public function defaultWeight(): int
    {
        return match ($this) {
            self::NinExact => 100,
            self::PhoneExact => 30,
            self::DobExact => 25,
            self::DobYear => 8,
            self::SexSame => 5,
            self::SexDiffers => -40,
            self::SurnameExact => 20,
            self::SurnameSounds => 12,
            self::FirstNameExact => 15,
            self::FirstNameSounds => 8,
            self::NamesSwapped => 15,
            self::LgaSame => 5,
            self::ParentSame => 15,
            self::MotherLinked => 25,
        };
    }
}
