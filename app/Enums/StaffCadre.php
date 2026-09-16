<?php

namespace App\Enums;

/**
 * The professional cadre of a member of staff (PRD USR-01). Clinical cadres
 * may be named as the provider on an encounter (USR-05); the others enter
 * data on a provider's behalf.
 */
enum StaffCadre: string
{
    case Doctor = 'doctor';
    case Nurse = 'nurse';
    case Midwife = 'midwife';
    case Chew = 'chew';
    case Pharmacist = 'pharmacist';
    case PharmacyTechnician = 'pharmacy_technician';
    case LabScientist = 'lab_scientist';
    case LabTechnician = 'lab_technician';
    case RecordsOfficer = 'records_officer';
    case DataClerk = 'data_clerk';
    case Cashier = 'cashier';
    case Administrator = 'administrator';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Doctor => 'Doctor',
            self::Nurse => 'Nurse',
            self::Midwife => 'Midwife',
            self::Chew => 'Community health extension worker',
            self::Pharmacist => 'Pharmacist',
            self::PharmacyTechnician => 'Pharmacy technician',
            self::LabScientist => 'Medical laboratory scientist',
            self::LabTechnician => 'Laboratory technician',
            self::RecordsOfficer => 'Health records officer',
            self::DataClerk => 'Data clerk',
            self::Cashier => 'Cashier',
            self::Administrator => 'Administrator',
            self::Other => 'Other',
        };
    }

    /**
     * Whether staff of this cadre may be recorded as the provider responsible
     * for an encounter.
     */
    public function isClinical(): bool
    {
        return in_array($this, [
            self::Doctor, self::Nurse, self::Midwife, self::Chew,
            self::Pharmacist, self::LabScientist,
        ], true);
    }

    /**
     * The professional body that issues this cadre's registration number,
     * when there is one.
     */
    public function registrationBody(): ?string
    {
        return match ($this) {
            self::Doctor => 'MDCN',
            self::Nurse, self::Midwife => 'NMCN',
            self::Chew => 'CHPRBN',
            self::Pharmacist, self::PharmacyTechnician => 'PCN',
            self::LabScientist, self::LabTechnician => 'MLSCN',
            self::RecordsOfficer => 'HRORBN',
            default => null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, clinical: bool, registration_body: string|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'clinical' => $c->isClinical(),
            'registration_body' => $c->registrationBody(),
        ], self::cases());
    }
}
