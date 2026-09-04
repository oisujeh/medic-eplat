<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import AllergyDialog from '@/components/clinical-record/AllergyDialog.vue';
import AddendaPanel from '@/components/encounter/AddendaPanel.vue';
import DispositionBar from '@/components/encounter/DispositionBar.vue';
import EncounterSidebar from '@/components/encounter/EncounterSidebar.vue';
import EncounterStepper from '@/components/encounter/EncounterStepper.vue';
import type { Stage } from '@/components/encounter/EncounterStepper.vue';
import EncounterTimeline from '@/components/encounter/EncounterTimeline.vue';
import PatientBanner from '@/components/encounter/PatientBanner.vue';
import AssessmentStage from '@/components/encounter/stages/AssessmentStage.vue';
import ExaminationStage from '@/components/encounter/stages/ExaminationStage.vue';
import FollowUpStage from '@/components/encounter/stages/FollowUpStage.vue';
import NursingStage from '@/components/encounter/stages/NursingStage.vue';
import PlanStage from '@/components/encounter/stages/PlanStage.vue';
import SubjectiveStage from '@/components/encounter/stages/SubjectiveStage.vue';
import type { SurveillanceCaseFlag } from '@/components/encounter/SurveillanceBanner.vue';
import SurveillanceBanner from '@/components/encounter/SurveillanceBanner.vue';
import { Button } from '@/components/ui/button';
import { provideEncounterForm } from '@/composables/useEncounterForm';
import type {
    Allergy,
    Encounter,
    EncounterAddendum,
    EncounterSummary,
    Immunization,
    LabResult,
    LabTest,
    Medication,
    ObservationCodeDefinition,
    ObservationSet,
    Option,
    PatientAlert,
    PatientBanner as PatientBannerData,
    Problem,
    Referral,
    ReferralDraft,
    ServicePointOption,
} from '@/types/clinical';

const props = defineProps<{
    encounter: Encounter;
    patient: PatientBannerData;
    allergies: Allergy[];
    problems: Problem[];
    medications: Medication[];
    labResults: LabResult[];
    alerts: PatientAlert[];
    surveillanceCases: SurveillanceCaseFlag[];
    referrals: Referral[];
    referralDraft: ReferralDraft;
    immunizations: Immunization[];
    addenda: EncounterAddendum[];
    observationSets: ObservationSet[];
    observationCodes: ObservationCodeDefinition[];
    labCatalog: LabTest[];
    pastEncounters: EncounterSummary[];
    onwardServicePoints: ServicePointOption[];
    priorities: Option[];
    outcomes: Option[];
    can: { document: boolean; sign: boolean; addend: boolean };
}>();

const isConsultation = props.encounter.type === 'consultation';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Encounters', href: '#' },
            { title: 'Encounter', href: '#' },
        ],
    },
});

// The stage list is decided by the encounter type; history is always there.
const stages: Stage[] = isConsultation
    ? [
          { key: 'subjective', label: 'Subjective' },
          { key: 'examination', label: 'Examination' },
          { key: 'assessment', label: 'Assessment' },
          { key: 'plan', label: 'Plan' },
          { key: 'follow_up', label: 'Follow-up' },
      ]
    : [{ key: 'nursing', label: 'Nursing note' }];

const { activeStage, goToStage, readOnly } = provideEncounterForm(
    props.encounter,
    props.can.document,
    stages[0].key,
);

const stageIndex = computed(() =>
    stages.findIndex((s) => s.key === activeStage.value),
);

function goPrev() {
    if (stageIndex.value > 0) {
        goToStage(stages[stageIndex.value - 1].key);
    }
}

function goNext() {
    if (stageIndex.value >= 0 && stageIndex.value < stages.length - 1) {
        goToStage(stages[stageIndex.value + 1].key);
    }
}

const latestObservations = computed<ObservationSet | null>(
    () => props.observationSets[0] ?? null,
);

const sidebar = ref<InstanceType<typeof EncounterSidebar> | null>(null);
const safetyAlerts = computed(() => sidebar.value?.safetyAlerts ?? []);

const allergiesOpen = ref(false);

const backLabel = isConsultation ? 'Back to clinical' : 'Back to nursing';
</script>

<template>
    <Head :title="`${encounter.type_label} — ${patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 p-4">
        <Link
            :href="encounter.urls.console"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ backLabel }}
        </Link>

        <PatientBanner
            :patient="patient"
            :encounter="encounter"
            :allergies="allergies"
            @manage-allergies="allergiesOpen = true"
        />

        <SurveillanceBanner :cases="surveillanceCases" />

        <div
            v-if="readOnly && !encounter.is_open"
            class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 px-4 py-2 text-sm text-emerald-800 dark:text-emerald-300"
        >
            This {{ encounter.type_label.toLowerCase() }} was signed
            <span v-if="encounter.author">by {{ encounter.author }}</span>
            <span v-if="encounter.signed_at_label"
                >on {{ encounter.signed_at_label }}</span
            >
            and is read-only.
        </div>
        <div
            v-else-if="readOnly"
            class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-2 text-sm text-amber-800 dark:text-amber-300"
        >
            You can view this encounter but not document it.
        </div>

        <div
            class="grid gap-4 lg:grid-cols-[1fr_20rem] xl:grid-cols-[1fr_22rem]"
        >
            <!-- Not a <form>: the record panels carry their own forms. -->
            <div class="order-1 flex flex-col gap-4">
                <EncounterStepper
                    v-model="activeStage"
                    :stages="stages"
                    :history-count="pastEncounters.length"
                />

                <template v-if="isConsultation">
                    <SubjectiveStage v-show="activeStage === 'subjective'" />
                    <ExaminationStage
                        v-show="activeStage === 'examination'"
                        :observations="latestObservations"
                        :observation-codes="observationCodes"
                    />
                    <AssessmentStage
                        v-show="activeStage === 'assessment'"
                        :problems="problems"
                        :observations="latestObservations"
                        :safety-alerts="safetyAlerts"
                    />
                    <PlanStage
                        v-show="activeStage === 'plan'"
                        :medications="medications"
                        :lab-results="labResults"
                        :lab-catalog="labCatalog"
                    />
                    <FollowUpStage
                        v-show="activeStage === 'follow_up'"
                        :outcomes="outcomes"
                        :clinics="onwardServicePoints"
                        :referrals="referrals"
                        :referral-draft="referralDraft"
                        :priorities="priorities"
                    />
                </template>
                <NursingStage
                    v-else
                    v-show="activeStage === 'nursing'"
                    :observations="latestObservations"
                    :observation-codes="observationCodes"
                    :immunizations="immunizations"
                />

                <section
                    v-show="activeStage === 'history'"
                    class="rounded-xl border border-border bg-card p-5"
                >
                    <h2 class="mb-3 text-base font-semibold">
                        Past encounters
                    </h2>
                    <EncounterTimeline
                        :encounters="pastEncounters"
                        empty-text="No previous encounters."
                    />
                </section>

                <div
                    v-if="stages.length > 1 && stageIndex >= 0"
                    class="flex items-center justify-between"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="stageIndex === 0"
                        @click="goPrev"
                    >
                        <ChevronLeft class="size-4" />
                        Back
                    </Button>
                    <Button
                        v-if="stageIndex < stages.length - 1"
                        type="button"
                        variant="outline"
                        @click="goNext"
                    >
                        Continue to {{ stages[stageIndex + 1].label }}
                        <ChevronRight class="size-4" />
                    </Button>
                </div>

                <AddendaPanel
                    v-if="!encounter.is_open"
                    :addenda="addenda"
                    :action="encounter.urls.addenda"
                    :can-addend="can.addend"
                />

                <DispositionBar
                    :onward-service-points="onwardServicePoints"
                    :priorities="priorities"
                    :can-sign="can.sign"
                />
            </div>

            <EncounterSidebar
                ref="sidebar"
                class="order-2"
                :observations="latestObservations"
                :alerts="alerts"
                :allergies="allergies"
                :problems="problems"
                :medications="medications"
                :lab-results="labResults"
                :patient-url="patient.url"
                :stages="
                    isConsultation
                        ? {
                              problems: 'assessment',
                              medications: 'plan',
                              labs: 'plan',
                          }
                        : {}
                "
                @go="goToStage"
            />
        </div>

        <AllergyDialog
            v-model:open="allergiesOpen"
            :allergies="allergies"
            :action="encounter.urls.allergies"
            :disabled="readOnly"
        />
    </div>
</template>
