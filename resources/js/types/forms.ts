/**
 * The forms engine's JSON vocabulary (PRD 4.3), mirroring
 * app/Support/Forms on the server. A definition is pages of sections of
 * fields; a section may repeat. Conditions and calculations are plain
 * JSON trees evaluated identically on both sides.
 */

export type FormFieldType =
    | 'numeric'
    | 'coded'
    | 'text'
    | 'date'
    | 'datetime'
    | 'boolean'
    | 'drug'
    | 'diagnosis'
    | 'lab_test'
    | 'calculated';

/** A label, or the same label in several languages keyed by locale. */
export type FormLabel = string | Record<string, string>;

export type FieldReference = { field: string };
export type ContextReference = { context: string };

export type ConditionOperator =
    | 'eq'
    | 'ne'
    | 'gt'
    | 'gte'
    | 'lt'
    | 'lte'
    | 'in'
    | 'not_in'
    | 'empty'
    | 'not_empty'
    | 'contains';

export type FormCondition =
    | { all: FormCondition[] }
    | { any: FormCondition[] }
    | { not: FormCondition }
    | {
          field: string;
          op: ConditionOperator;
          value?: unknown | FieldReference | ContextReference;
      };

export type FormCalculation =
    | number
    | FieldReference
    | ContextReference
    | { op: string; args: Array<FormCalculation | FormCondition> };

export type FormOption = {
    value: string | number;
    label: FormLabel;
    concept?: string;
    concept_id?: number;
};

export type FormField = {
    key: string;
    type: FormFieldType;
    label: FormLabel;
    concept?: string;
    concept_id?: number;
    help?: FormLabel;
    placeholder?: FormLabel;
    required?: boolean;
    unit?: string;
    min?: number;
    max?: number;
    step?: number;
    integer?: boolean;
    max_length?: number;
    rows?: number;
    options?: FormOption[];
    multiple?: boolean;
    default?: unknown;
    not_future?: boolean;
    show_when?: FormCondition;
    calc?: FormCalculation;
    validate?: Array<{ when: FormCondition; message: string }>;
};

export type FormSection = {
    key: string;
    title?: FormLabel;
    description?: FormLabel;
    concept?: string;
    concept_id?: number;
    repeat?: { min?: number; max?: number; add_label?: string };
    show_when?: FormCondition;
    fields: FormField[];
};

export type FormPage = {
    key: string;
    title?: FormLabel;
    description?: FormLabel;
    show_when?: FormCondition;
    sections: FormSection[];
};

export type FormDefinition = { pages: FormPage[] };

/** The answers: field key to value; a repeating section to its instances. */
export type FormData = Record<string, unknown>;
export type FormInstance = Record<string, unknown>;

/** What a form may read about the patient it is filled for. */
export type FormContext = {
    age: number | null;
    age_days: number | null;
    sex: string | null;
    encounter_at: string | null;
    today: string;
};

export type FormEntryMode = 'guided' | 'rapid';
export type FormSubmissionStatus = 'draft' | 'completed' | 'voided';

/** Answers that name a catalogue entry rather than a scalar. */
export type DrugAnswer = { id: number; name: string };
export type DiagnosisAnswer = { code: string; description: string };
export type LabTestAnswer = { id: number; name: string };

/** FormSubmissionSummaryResource */
export type FormSubmissionSummary = {
    id: number;
    form_id: number;
    form_name: string;
    form_slug: string;
    version: number;
    status: FormSubmissionStatus;
    status_label: string;
    entry_mode: FormEntryMode;
    by: string | null;
    at: string | null;
    at_label: string | null;
    void_reason: string | null;
    url: string;
};

export type AvailableForm = {
    id: number;
    name: string;
    description: string | null;
    url: string;
};
