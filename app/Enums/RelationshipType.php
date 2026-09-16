<?php

namespace App\Enums;

/**
 * How two patients on the register are related (PRD REG-18). Stored from
 * the first patient's point of view; `inverse()` gives the other side.
 */
enum RelationshipType: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Child = 'child';
    case Spouse = 'spouse';
    case Sibling = 'sibling';
    case Guardian = 'guardian';
    case Ward = 'ward';
    case HouseholdMember = 'household_member';
    case Contact = 'contact';
    case IndexCase = 'index_case';

    public function label(): string
    {
        return match ($this) {
            self::Mother => 'Mother',
            self::Father => 'Father',
            self::Child => 'Child',
            self::Spouse => 'Spouse',
            self::Sibling => 'Sibling',
            self::Guardian => 'Guardian',
            self::Ward => 'Ward (dependant)',
            self::HouseholdMember => 'Household member',
            self::Contact => 'Contact',
            self::IndexCase => 'Index case',
        };
    }

    /**
     * The relationship as seen from the other patient.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::Mother, self::Father => self::Child,
            self::Child => self::Mother,
            self::Guardian => self::Ward,
            self::Ward => self::Guardian,
            self::Contact => self::IndexCase,
            self::IndexCase => self::Contact,
            self::Spouse, self::Sibling, self::HouseholdMember => $this,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
