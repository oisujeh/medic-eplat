<?php

namespace App\Support;

class PatientOptions
{
    /** @var array<int, string> */
    public const TITLES = ['Mr', 'Mrs', 'Miss', 'Ms', 'Master', 'Dr', 'Prof', 'Chief', 'Alhaji', 'Alhaja', 'Rev', 'Pastor'];

    /** @var array<string, string> */
    public const SEXES = ['F' => 'Female', 'M' => 'Male'];

    /** @var array<int, string> */
    public const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];

    /** @var array<int, string> */
    public const NOK_RELATIONSHIPS = ['Spouse', 'Parent', 'Child', 'Sibling', 'Relative', 'Guardian', 'Friend', 'Other'];

    /** @var array<string, string> */
    public const COVERAGES = ['private' => 'Private / out-of-pocket', 'hmo' => 'HMO-covered'];

    /** @var array<int, string> */
    public const VISIT_CATEGORIES = [
        'Outpatient',
        'Accident & Emergency',
        'Ward A — General',
        'Ward B — Infectious disease',
        'Ward C — Pediatrics',
        'Ward D — Maternity',
        'ICU',
        'Eye clinic',
        'Dental clinic',
    ];

    /** @var array<int, string> */
    public const OUTPATIENT_SERVICES = [
        'Antenatal Care (ANC)',
        'Family Planning',
        'Clinical Consultation & Diagnosis',
        'Labor & Delivery',
        'Child Follow-up & Immunization',
        'HIV Clinic',
        'Tuberculosis (TB) Clinic',
        'Nutrition',
        'Pharmacy Refill',
        'Other',
    ];

    /** @var array<int, string> */
    public const HMO_PROVIDERS = ['Hygeia HMO', 'Reliance HMO', 'NHIS', 'AXA Mansard', 'Avon HMO', 'Leadway Health', 'Other'];

    /** @var array<int, string> */
    public const VISIT_REASONS = ['New visit', 'Returning / follow-up', 'Appointment', 'Referral', 'Emergency'];
}
