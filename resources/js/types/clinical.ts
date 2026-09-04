/**
 * Shapes shared by every clinical screen. Each mirrors a JSON resource under
 * app/Http/Resources, which is the only place the backend builds them.
 */

export type Option = { value: string; label: string };

export type Personnel = { id: number; name: string };

export type ServicePointOption = {
    id: number;
    name: string;
    personnel: Personnel[];
};

/** PatientBannerResource */
export type PatientBanner = {
    id: number;
    name: string;
    initials: string;
    file_number: string;
    sex: string;
    sex_label: string;
    age: number | null;
    dob: string | null;
    phone: string | null;
    address: string | null;
    url: string;
};

export type EncounterType =
    'consultation' | 'triage' | 'nursing' | 'ward_round' | 'discharge';

/** EncounterAddendumResource */
export type EncounterAddendum = {
    id: number;
    body: string;
    author: string | null;
    recorded_at: string;
    recorded_at_label: string;
};

export type EncounterUrls = {
    show: string;
    update: string;
    sign: string;
    follow_up: string;
    addenda: string;
    problems: string;
    medications: string;
    allergies: string;
    lab_orders: string;
    immunizations: string;
    referrals: string;
    observations: string;
    console: string;
};

/** ReferralResource */
export type Referral = {
    id: number;
    referral_number: string;
    encounter_id: number | null;
    urgency: string;
    urgency_label: string;
    destination_facility: string;
    destination_department: string | null;
    destination_contact: string | null;
    reason: string;
    diagnosis: string | null;
    clinical_summary: string | null;
    treatment_given: string | null;
    status: string;
    status_label: string;
    status_tone: string;
    is_open: boolean;
    feedback: string | null;
    feedback_at: string | null;
    referred_at: string;
    printed_at: string | null;
    urls: { show: string; letter: string; status: string };
};

/** What the referral form is pre-filled with from the encounter. */
export type ReferralDraft = {
    diagnosis: string | null;
    clinical_summary: string | null;
    treatment_given: string | null;
};

/** EncounterResource */
export type Encounter = {
    id: number;
    type: EncounterType;
    type_label: string;
    status: string;
    status_label: string;
    is_open: boolean;
    service_point: string | null;
    service_slug: string | null;
    captures_observations: boolean;
    visit_number: string | null;
    visit_date: string | null;
    author: string | null;
    started_at: string | null;
    signed_at: string | null;
    signed_at_label: string | null;
    presenting_complaint: string | null;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    structured: Record<string, unknown> | null;
    outcome: string | null;
    follow_up_at: string | null;
    urls: EncounterUrls;
};

/** EncounterSummaryResource */
export type EncounterSummary = {
    id: number;
    type: EncounterType;
    type_label: string;
    status: string;
    status_label: string;
    service_point: string | null;
    author: string | null;
    date: string | null;
    date_label: string | null;
    ago: string | null;
    presenting_complaint: string | null;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    diagnoses: string[];
    outcome: string | null;
    addenda?: EncounterAddendum[];
    url: string;
};

export type AlertLevel = 'normal' | 'warning' | 'critical';

export type ObservationReading = {
    code: string;
    label: string;
    short_label: string;
    panel: string;
    value: number | string;
    unit: string | null;
    display: string;
    level: AlertLevel;
    flag: string | null;
};

export type ObservationFlag = { metric: string; level: string; label: string };

/** ObservationSetResource */
export type ObservationSet = {
    id: number;
    values: Record<string, number | string>;
    blood_pressure: string | null;
    readings: ObservationReading[];
    notes: string | null;
    recorded_by: string | null;
    recorded_at: string;
    recorded_at_label: string;
    recorded_at_short: string;
    recorded_at_diff: string;
    alert_level: AlertLevel;
    flags: ObservationFlag[];
};

/** ObservationCode::definitions() */
export type ObservationCodeDefinition = {
    value: string;
    label: string;
    short_label: string;
    unit: string | null;
    step: number;
    panel: string;
    text: boolean;
    derived: boolean;
    min: number | null;
    max: number | null;
};

/** ProblemResource */
export type Problem = {
    id: number;
    name: string;
    code: string | null;
    status: string;
    role: string | null;
    encounter_id: number | null;
};

/** MedicationResource */
export type Medication = {
    id: number;
    label: string;
    name: string;
    dose: string | null;
    frequency: string | null;
    route: string | null;
    status: string;
};

/** AllergyResource */
export type Allergy = {
    id: number;
    substance: string;
    reaction: string | null;
    severity: string | null;
    category: string | null;
};

/** LabResultResource */
export type LabResult = {
    id: number;
    name: string;
    code: string | null;
    value: string | null;
    unit: string | null;
    display_value: string;
    reference_range: string | null;
    flag: string | null;
    status: string;
    specimen: string | null;
    resulted_at: string | null;
};

/** LabTestResource */
export type LabTest = {
    id: number;
    code: string;
    name: string;
    department: string;
    department_label: string;
    specimen: string | null;
    is_panel: boolean;
    component_count: number;
    reference: string | null;
};

/** ImmunizationResource */
export type Immunization = {
    id: number;
    label: string;
    vaccine: string;
    dose_label: string | null;
    batch_no: string | null;
    site: string | null;
    route: string | null;
    administered_at: string | null;
};

/** PatientAlertResource */
export type PatientAlert = { message: string; severity: string };

/** A queue entry as the clinical and nursing consoles list it. */
export type WorklistEntry = {
    id: number;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    service_point: string;
    assigned_to: string | null;
    assigned_to_me: boolean;
    waiting_since: string | null;
    latest_observations: ObservationSet | null;
    open_url: string;
    patient: {
        name: string;
        initials: string;
        file_number: string;
        sex: string;
        age: number | null;
    };
};

/** A recently signed encounter on a console. */
export type RecentEncounter = {
    id: number;
    patient_name: string;
    file_number: string;
    summary: string | null;
    signed_at: string | null;
    url: string;
    patient_url: string;
};
