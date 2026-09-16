<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { useEncounterContext } from '@/composables/useEncounterForm';
import { autoGrow, textareaClass } from '@/lib/forms';

const { form, readOnly } = useEncounterContext();

const histories = [
    {
        key: 'past_medical_history',
        label: 'Past medical history',
        placeholder: 'Chronic conditions, surgeries, admissions…',
    },
    {
        key: 'family_history',
        label: 'Family history',
        placeholder: 'Relevant hereditary / family conditions…',
    },
    {
        key: 'social_history',
        label: 'Social history',
        placeholder: 'Alcohol, tobacco, occupation, living situation…',
    },
    {
        key: 'medication_history',
        label: 'Medication history',
        placeholder: 'Current & recent medications…',
    },
] as const;
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5">
        <h2 class="mb-4 text-base font-semibold">Subjective</h2>
        <div class="flex flex-col gap-4">
            <div
                class="grid gap-1.5 rounded-lg border border-blue-400/60 bg-blue-500/5 p-3 dark:border-blue-400/40"
            >
                <Label class="text-blue-800 dark:text-blue-300"
                    >Chief complaint <span class="text-red-500">*</span></Label
                >
                <textarea
                    v-model="form.presenting_complaint"
                    :class="textareaClass"
                    class="bg-background"
                    rows="2"
                    :disabled="readOnly"
                    placeholder="Why is the patient here? e.g. Persistent cough for 3 weeks"
                    @input="autoGrow"
                />
                <InputError :message="form.errors.presenting_complaint" />
            </div>
            <div class="grid gap-1.5">
                <Label>History of present illness</Label>
                <textarea
                    v-model="form.subjective"
                    :class="textareaClass"
                    rows="3"
                    :disabled="readOnly"
                    placeholder="Onset, duration, associated symptoms, aggravating / relieving factors…"
                    @input="autoGrow"
                />
                <InputError :message="form.errors.subjective" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div v-for="h in histories" :key="h.key" class="grid gap-1.5">
                    <Label>{{ h.label }}</Label>
                    <textarea
                        v-model="form.structured.subjective[h.key]"
                        :class="textareaClass"
                        rows="2"
                        :disabled="readOnly"
                        :placeholder="h.placeholder"
                        @input="autoGrow"
                    />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label>Allergy history</Label>
                <textarea
                    v-model="form.structured.subjective.allergy_history"
                    :class="textareaClass"
                    rows="2"
                    :disabled="readOnly"
                    placeholder="Documented allergies and reactions (manage the alert list from the banner above)…"
                    @input="autoGrow"
                />
            </div>
            <Collapsible v-slot="{ open }">
                <CollapsibleTrigger
                    class="flex w-full items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2 text-sm font-medium"
                >
                    Review of systems (optional)
                    <ChevronDown
                        class="size-4 text-muted-foreground transition-transform"
                        :class="{ 'rotate-180': open }"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent class="pt-2">
                    <textarea
                        v-model="form.structured.subjective.review_of_systems"
                        :class="textareaClass"
                        rows="3"
                        :disabled="readOnly"
                        placeholder="Systematic screen — constitutional, cardiovascular, respiratory, GI, GU, neuro…"
                        @input="autoGrow"
                    />
                </CollapsibleContent>
            </Collapsible>
        </div>
    </section>
</template>
