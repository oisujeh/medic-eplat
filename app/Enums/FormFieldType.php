<?php

namespace App\Enums;

/**
 * The kinds of field a form definition may declare (PRD FRM-02). Each type
 * says how an answer is validated and which column of the flattened value
 * table it lands in.
 */
enum FormFieldType: string
{
    case Numeric = 'numeric';
    case Coded = 'coded';
    case Text = 'text';
    case Date = 'date';
    case Datetime = 'datetime';
    case Boolean = 'boolean';
    case Drug = 'drug';
    case Diagnosis = 'diagnosis';
    case LabTest = 'lab_test';
    case Calculated = 'calculated';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Number',
            self::Coded => 'Coded choice',
            self::Text => 'Text',
            self::Date => 'Date',
            self::Datetime => 'Date and time',
            self::Boolean => 'Yes / No',
            self::Drug => 'Drug',
            self::Diagnosis => 'Diagnosis (ICD-10)',
            self::LabTest => 'Laboratory test',
            self::Calculated => 'Calculated',
        };
    }

    /**
     * How the answer is stored in the flattened value table.
     */
    public function valueType(): string
    {
        return match ($this) {
            self::Numeric, self::Calculated => 'numeric',
            self::Coded, self::Drug => 'coded',
            self::Text, self::Diagnosis, self::LabTest => 'text',
            self::Date => 'date',
            self::Datetime => 'datetime',
            self::Boolean => 'boolean',
        };
    }

    /**
     * Whether the field is filled by the engine rather than the user.
     */
    public function isDerived(): bool
    {
        return $this === self::Calculated;
    }

    /**
     * Whether the answer is an object naming a catalogue entry rather than
     * a scalar.
     */
    public function isReference(): bool
    {
        return in_array($this, [self::Drug, self::Diagnosis, self::LabTest], true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
