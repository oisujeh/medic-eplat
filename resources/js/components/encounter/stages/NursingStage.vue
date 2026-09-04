<script setup lang="ts">
import { computed, ref } from 'vue';
import ImmunizationPanel from '@/components/clinical-record/ImmunizationPanel.vue';
import InputError from '@/components/InputError.vue';
import ObservationSetForm from '@/components/observations/ObservationSetForm.vue';
import ObservationTiles from '@/components/observations/ObservationTiles.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEncounterContext } from '@/composables/useEncounterForm';
import { autoGrow, textareaClass } from '@/lib/forms';
import type {
    Immunization,
    ObservationCodeDefinition,
    ObservationSet,
} from '@/types/clinical';

/**
 * The nursing note: observations taken at the point, the narrative, and the
 * service-specific extras (family planning, immunizations).
 */
const props = defineProps<{
    observations: ObservationSet | null;
    observationCodes: ObservationCodeDefinition[];
    immunizations: Immunization[];
}>();

const { encounter, form, readOnly } = useEncounterContext();

const service = computed(() => encounter.service_slug ?? '');
const isAnc = computed(() => service.value === 'anc');
const isFamilyPlanning = computed(() => service.value === 'family-planning');
const isImmunization = computed(
    () => service.value === 'immunization' || props.immunizations.length > 0,
);

const panels = computed(() =>
    isAnc.value
        ? ['vitals', 'anthropometrics', 'antenatal']
        : ['vitals', 'anthropometrics'],
);

const tileCodes = computed(() =>
    isAnc.value
        ? [
              'blood_pressure',
              'weight',
              'gestational_age',
              'fundal_height',
              'fetal_heart_rate',
              'presentation',
          ]
        : [
              'temperature',
              'blood_pressure',
              'weight',
              'pulse',
              'respiratory_rate',
              'spo2',
          ],
);

const recording = ref(false);
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold">
                    {{ isAnc ? 'Antenatal observations' : 'Observations' }}
                </h2>
                <Button
                    v-if="
                        !readOnly &&
                        !recording &&
                        encounter.captures_observations
                    "
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="recording = true"
                >
                    {{
                        observations ? 'Record new set' : 'Record observations'
                    }}
                </Button>
                <span
                    v-else-if="observations"
                    class="text-xs text-muted-foreground"
                    >Recorded {{ observations.recorded_at_diff }}</span
                >
            </div>
            <div
                v-if="recording"
                class="mb-4 rounded-lg border border-border bg-muted/30 p-3"
            >
                <ObservationSetForm
                    :action="encounter.urls.observations"
                    :codes="observationCodes"
                    :panels="panels"
                    :context="{ encounter_id: encounter.id }"
                    compact
                    @saved="recording = false"
                    @cancel="recording = false"
                />
            </div>
            <ObservationTiles
                :set="observations"
                :codes="tileCodes"
                :columns="6"
            />
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 text-base font-semibold">Nursing note</h2>
            <div class="flex flex-col gap-4">
                <div class="grid gap-1.5">
                    <Label for="subjective">Patient report</Label>
                    <textarea
                        id="subjective"
                        v-model="form.subjective"
                        rows="2"
                        :class="textareaClass"
                        :disabled="readOnly"
                        placeholder="What the patient reports…"
                        @input="autoGrow"
                    />
                    <InputError :message="form.errors.subjective" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="objective">Nursing observations</Label>
                    <textarea
                        id="objective"
                        v-model="form.objective"
                        rows="3"
                        :class="textareaClass"
                        :disabled="readOnly"
                        placeholder="What you observed on assessment…"
                        @input="autoGrow"
                    />
                    <InputError :message="form.errors.objective" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="assessment">Nursing assessment</Label>
                    <textarea
                        id="assessment"
                        v-model="form.assessment"
                        rows="2"
                        :class="textareaClass"
                        :disabled="readOnly"
                        placeholder="Nursing diagnosis / impression…"
                        @input="autoGrow"
                    />
                    <InputError :message="form.errors.assessment" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="plan">Intervention / care given</Label>
                    <textarea
                        id="plan"
                        v-model="form.plan"
                        rows="3"
                        :class="textareaClass"
                        :disabled="readOnly"
                        placeholder="Procedures, care and treatments provided…"
                        @input="autoGrow"
                    />
                    <InputError :message="form.errors.plan" />
                </div>

                <div
                    v-if="isFamilyPlanning"
                    class="grid gap-3 border-t border-border pt-4 sm:grid-cols-2"
                >
                    <div class="grid gap-1.5">
                        <Label>Method</Label>
                        <Input
                            v-model="form.structured.family_planning.method"
                            placeholder="e.g. IUD, Implant, Injectable"
                            :disabled="readOnly"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Counseling</Label>
                        <textarea
                            v-model="form.structured.family_planning.counseling"
                            rows="2"
                            :class="textareaClass"
                            :disabled="readOnly"
                            @input="autoGrow"
                        />
                    </div>
                </div>
            </div>
        </div>

        <ImmunizationPanel
            v-if="isImmunization"
            :immunizations="immunizations"
            :action="encounter.urls.immunizations"
            :disabled="readOnly"
        />
    </section>
</template>
