import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, inject, provide, ref } from 'vue';
import type { ComputedRef, InjectionKey, Ref } from 'vue';
import { toDatetimeLocal } from '@/lib/forms';
import type { Encounter } from '@/types/clinical';

// --- Catalogues for the structured stages ---
export const examSystems = [
    { key: 'cardiovascular', label: 'Cardiovascular' },
    { key: 'respiratory', label: 'Respiratory' },
    { key: 'abdomen', label: 'Abdomen' },
    { key: 'neurology', label: 'Neurology' },
    { key: 'ent', label: 'ENT' },
    { key: 'musculoskeletal', label: 'Musculoskeletal' },
    { key: 'skin', label: 'Skin' },
    { key: 'genitourinary', label: 'Genitourinary' },
];
export const appearances = [
    { value: 'well', label: 'Well' },
    { value: 'ill', label: 'Ill-looking' },
    { value: 'distressed', label: 'Distressed' },
];
export const generalToggles = [
    { key: 'pallor', label: 'Pallor' },
    { key: 'jaundice', label: 'Jaundice' },
    { key: 'cyanosis', label: 'Cyanosis' },
    { key: 'edema', label: 'Edema' },
] as const;
export const procedurePresets = [
    'ECG',
    'Dressing',
    'Suturing',
    'Nebulization',
    'Catheterization',
];
export const imagingPresets = [
    'Chest X-ray',
    'Ultrasound',
    'CT Scan',
    'MRI',
    'Echocardiogram',
];
export const referralPresets = [
    'Cardiology',
    'Surgery',
    'Nutrition',
    'Physiotherapy',
    'Psychiatry',
];
export const counselingOptions = [
    { value: 'diet', label: 'Diet counselling' },
    { value: 'smoking_cessation', label: 'Smoking cessation' },
    { value: 'medication_adherence', label: 'Medication adherence' },
    { value: 'physical_activity', label: 'Physical activity' },
    { value: 'family_planning', label: 'Family planning' },
];
export const monitoringGoals = [
    { value: 'repeat_bp', label: 'Repeat BP' },
    { value: 'cbc', label: 'CBC' },
    { value: 'weight', label: 'Weight' },
    { value: 'blood_sugar', label: 'Blood Sugar' },
    { value: 'renal_function', label: 'Renal function' },
];
export const followUpIntervals = [
    { value: '1w', label: '1 week' },
    { value: '2w', label: '2 weeks' },
    { value: '1m', label: '1 month' },
    { value: '3m', label: '3 months' },
    { value: '6m', label: '6 months' },
];

export type ConsultationStructured = {
    subjective: {
        past_medical_history: string;
        family_history: string;
        social_history: string;
        medication_history: string;
        allergy_history: string;
        review_of_systems: string;
    };
    examination: {
        general: {
            appearance: string;
            consciousness: string;
            hydration: string;
            pallor: boolean;
            jaundice: boolean;
            cyanosis: boolean;
            edema: boolean;
        };
        systems: Record<string, string>;
    };
    plan: {
        procedures: string[];
        imaging: string[];
        referrals: string[];
        counseling: string[];
    };
    follow_up: {
        interval: string;
        monitoring_goals: string[];
        patient_instructions: string;
    };
};

export type NursingStructured = {
    family_planning: { method: string; counseling: string };
};

export type EncounterStructured = ConsultationStructured & NursingStructured;

export type EncounterFormData = {
    presenting_complaint: string;
    subjective: string;
    objective: string;
    assessment: string;
    plan: string;
    structured: EncounterStructured;
    outcome: string;
    follow_up_at: string;
    next_service_point_id: string;
    next_assigned_to: string;
    next_priority: string;
    next_note: string;
};

type Loose = Record<string, unknown> | null | undefined;

const str = (v: unknown): string => (typeof v === 'string' ? v : '');
const bool = (v: unknown): boolean => v === true;
const list = (v: unknown): string[] =>
    Array.isArray(v) ? v.filter((x): x is string => typeof x === 'string') : [];
const obj = (v: unknown): Record<string, unknown> =>
    v && typeof v === 'object' ? (v as Record<string, unknown>) : {};

/**
 * Build the structured payload from the saved encounter, backfilling gaps with
 * empty defaults so every field is bound.
 */
export function buildStructured(src: Loose): EncounterStructured {
    const s = obj(src);
    const subjective = obj(s.subjective);
    const examination = obj(s.examination);
    const general = obj(examination.general);
    const savedSystems = obj(examination.systems);
    const plan = obj(s.plan);
    const followUp = obj(s.follow_up);
    const fp = obj(s.family_planning);

    const systems: Record<string, string> = {};

    for (const sys of examSystems) {
        systems[sys.key] = str(savedSystems[sys.key]);
    }

    return {
        subjective: {
            past_medical_history: str(subjective.past_medical_history),
            family_history: str(subjective.family_history),
            social_history: str(subjective.social_history),
            medication_history: str(subjective.medication_history),
            allergy_history: str(subjective.allergy_history),
            review_of_systems: str(subjective.review_of_systems),
        },
        examination: {
            general: {
                appearance: str(general.appearance),
                consciousness: str(general.consciousness),
                hydration: str(general.hydration),
                pallor: bool(general.pallor),
                jaundice: bool(general.jaundice),
                cyanosis: bool(general.cyanosis),
                edema: bool(general.edema),
            },
            systems,
        },
        plan: {
            procedures: list(plan.procedures),
            imaging: list(plan.imaging),
            referrals: list(plan.referrals),
            counseling: list(plan.counseling),
        },
        follow_up: {
            interval: str(followUp.interval),
            monitoring_goals: list(followUp.monitoring_goals),
            patient_instructions: str(followUp.patient_instructions),
        },
        family_planning: {
            method: str(fp.method),
            counseling: str(fp.counseling),
        },
    };
}

export type EncounterContext = {
    encounter: Encounter;
    form: InertiaForm<EncounterFormData>;
    /** True when the encounter is signed or the viewer may not document it. */
    readOnly: ComputedRef<boolean>;
    activeStage: Ref<string>;
    goToStage: (key: string) => void;
    saveDraft: () => void;
    sign: () => void;
};

const KEY: InjectionKey<EncounterContext> = Symbol('encounter');

/**
 * Create the documentation form for an encounter and share it with the stage
 * components below via provide/inject, so no stage mutates a prop.
 */
export function provideEncounterForm(
    encounter: Encounter,
    canDocument: boolean,
    initialStage: string,
): EncounterContext {
    const form = useForm<EncounterFormData>({
        presenting_complaint: encounter.presenting_complaint ?? '',
        subjective: encounter.subjective ?? '',
        objective: encounter.objective ?? '',
        assessment: encounter.assessment ?? '',
        plan: encounter.plan ?? '',
        structured: buildStructured(encounter.structured),
        outcome: encounter.outcome ?? '',
        follow_up_at: toDatetimeLocal(encounter.follow_up_at),
        next_service_point_id: 'none',
        next_assigned_to: 'none',
        next_priority: 'normal',
        next_note: '',
    });

    const readOnly = computed(() => !encounter.is_open || !canDocument);
    const activeStage = ref(initialStage);
    const isConsultation = encounter.type === 'consultation';

    // Only the structured keys the encounter type validates are sent.
    const structuredFor = (data: EncounterFormData) =>
        isConsultation
            ? {
                  subjective: data.structured.subjective,
                  examination: data.structured.examination,
                  plan: data.structured.plan,
                  follow_up: data.structured.follow_up,
              }
            : { family_planning: data.structured.family_planning };

    const narrative = (data: EncounterFormData) => ({
        presenting_complaint: data.presenting_complaint,
        subjective: data.subjective,
        objective: data.objective,
        assessment: data.assessment,
        plan: data.plan,
        structured: structuredFor(data),
        outcome: data.outcome || null,
        follow_up_at: data.follow_up_at || null,
    });

    const saveDraft = () => {
        form.transform((data) => narrative(data)).patch(encounter.urls.update, {
            preserveScroll: true,
            onFinish: () => form.transform((d) => d),
        });
    };

    const sign = () => {
        form.transform((data) => ({
            ...narrative(data),
            next_service_point_id:
                data.next_service_point_id === 'none'
                    ? null
                    : Number(data.next_service_point_id),
            next_assigned_to:
                data.next_assigned_to === 'none'
                    ? null
                    : Number(data.next_assigned_to),
            next_priority: data.next_priority,
            next_note: data.next_note,
        })).post(encounter.urls.sign, {
            preserveScroll: true,
            onFinish: () => form.transform((d) => d),
        });
    };

    const context: EncounterContext = {
        encounter,
        form,
        readOnly,
        activeStage,
        goToStage: (key: string) => {
            activeStage.value = key;
        },
        saveDraft,
        sign,
    };

    provide(KEY, context);

    return context;
}

/**
 * The encounter form shared by the page, for use inside a stage component.
 */
export function useEncounterContext(): EncounterContext {
    const context = inject(KEY);

    if (!context) {
        throw new Error(
            'useEncounterContext() must be used within an encounter page.',
        );
    }

    return context;
}
