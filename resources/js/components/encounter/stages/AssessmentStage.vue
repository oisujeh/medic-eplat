<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import DiagnosisPanel from '@/components/clinical-record/DiagnosisPanel.vue';
import InputError from '@/components/InputError.vue';
import ClinicalScores from '@/components/observations/ClinicalScores.vue';
import { Label } from '@/components/ui/label';
import { useEncounterContext } from '@/composables/useEncounterForm';
import { autoGrow, textareaClass } from '@/lib/forms';
import type { ObservationSet, Problem } from '@/types/clinical';

defineProps<{
    problems: Problem[];
    observations: ObservationSet | null;
    safetyAlerts: Array<{ label: string; level: string }>;
}>();

const { encounter, form, readOnly } = useEncounterContext();
</script>

<template>
    <section class="flex flex-col gap-4">
        <DiagnosisPanel
            :problems="problems"
            :action="encounter.urls.problems"
            :disabled="readOnly"
        />

        <div class="rounded-xl border border-border bg-card p-5">
            <Label>Clinical impression</Label>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Required to sign unless a primary or secondary diagnosis is
                coded above.
            </p>
            <textarea
                v-model="form.assessment"
                :class="textareaClass"
                class="mt-2"
                rows="3"
                :disabled="readOnly"
                placeholder="Reasoning — e.g. Patient presents with… likely uncontrolled hypertension…"
                @input="autoGrow"
            />
            <InputError :message="form.errors.assessment" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <ClinicalScores :set="observations" />
            <div
                v-if="safetyAlerts.length"
                class="rounded-xl border border-red-500/30 bg-red-500/5 p-5"
            >
                <h3
                    class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-red-700 dark:text-red-400"
                >
                    <AlertTriangle class="size-4" />
                    Safety flags
                </h3>
                <ul class="flex flex-col gap-1.5 text-sm">
                    <li
                        v-for="(a, i) in safetyAlerts"
                        :key="i"
                        class="flex items-start gap-1.5"
                        :class="
                            a.level === 'critical'
                                ? 'text-red-700 dark:text-red-400'
                                : 'text-amber-700 dark:text-amber-400'
                        "
                    >
                        <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                        {{ a.label }}
                    </li>
                </ul>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Derived from allergies and abnormal observations — not a
                    substitute for clinical judgement.
                </p>
            </div>
        </div>
    </section>
</template>
