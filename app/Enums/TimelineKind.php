<?php

namespace App\Enums;

/**
 * The kinds of event on a patient's longitudinal timeline.
 */
enum TimelineKind: string
{
    case Registration = 'registration';
    case Encounter = 'encounter';
    case Form = 'form';
    case Diagnosis = 'diagnosis';
    case Observation = 'observation';
    case LabOrder = 'lab_order';
    case LabResult = 'lab_result';
    case Medication = 'medication';
    case Dispense = 'dispense';
    case Immunization = 'immunization';
    case Admission = 'admission';
    case Discharge = 'discharge';
    case Delivery = 'delivery';
    case Referral = 'referral';
    case Surveillance = 'surveillance';
    case Death = 'death';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration',
            self::Encounter => 'Encounter',
            self::Form => 'Clinical form',
            self::Diagnosis => 'Diagnosis',
            self::Observation => 'Observations',
            self::LabOrder => 'Lab order',
            self::LabResult => 'Lab result',
            self::Medication => 'Medication',
            self::Dispense => 'Dispensing',
            self::Immunization => 'Immunization',
            self::Admission => 'Admission',
            self::Discharge => 'Discharge',
            self::Delivery => 'Delivery',
            self::Referral => 'Referral',
            self::Surveillance => 'IDSR case',
            self::Death => 'Death',
        };
    }

    /**
     * The Lucide icon the client draws for the kind.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Registration => 'UserPlus',
            self::Encounter => 'Stethoscope',
            self::Form => 'FileText',
            self::Diagnosis => 'ClipboardList',
            self::Observation => 'Activity',
            self::LabOrder, self::LabResult => 'FlaskConical',
            self::Medication => 'Pill',
            self::Dispense => 'Package',
            self::Immunization => 'Syringe',
            self::Admission => 'BedDouble',
            self::Discharge => 'LogOut',
            self::Delivery => 'Baby',
            self::Referral => 'Send',
            self::Surveillance => 'Radar',
            self::Death => 'HeartOff',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function definitions(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label(), 'icon' => $k->icon()], self::cases());
    }
}
