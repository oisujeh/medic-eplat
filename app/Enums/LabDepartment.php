<?php

namespace App\Enums;

/**
 * The analytical sections a laboratory test belongs to. Used to group the
 * compendium and to route work to the right bench in the lab worklist.
 */
enum LabDepartment: string
{
    case Haematology = 'haematology';
    case Chemistry = 'chemistry';
    case Microbiology = 'microbiology';
    case Serology = 'serology';
    case Molecular = 'molecular';
    case Immunology = 'immunology';
    case Urinalysis = 'urinalysis';
    case Histopathology = 'histopathology';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Haematology => 'Haematology',
            self::Chemistry => 'Clinical Chemistry',
            self::Microbiology => 'Microbiology',
            self::Serology => 'Serology',
            self::Molecular => 'Molecular / PCR',
            self::Immunology => 'Immunology',
            self::Urinalysis => 'Urinalysis',
            self::Histopathology => 'Histopathology',
        };
    }
}
