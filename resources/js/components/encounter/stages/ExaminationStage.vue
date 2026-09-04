<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ObservationSetForm from '@/components/observations/ObservationSetForm.vue';
import ObservationTiles from '@/components/observations/ObservationTiles.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    appearances,
    examSystems,
    generalToggles,
    useEncounterContext,
} from '@/composables/useEncounterForm';
import { autoGrow, textareaClass } from '@/lib/forms';
import type {
    ObservationCodeDefinition,
    ObservationSet,
} from '@/types/clinical';

defineProps<{
    observations: ObservationSet | null;
    observationCodes: ObservationCodeDefinition[];
}>();

const { encounter, form, readOnly } = useEncounterContext();

const recording = ref(false);
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold">Observations</h2>
                <Button
                    v-if="!readOnly && !recording"
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
                    :context="{ encounter_id: encounter.id }"
                    compact
                    @saved="recording = false"
                    @cancel="recording = false"
                />
            </div>
            <ObservationTiles :set="observations" :columns="6" />
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-4 text-base font-semibold">General examination</h2>
            <div class="flex flex-col gap-4">
                <div class="grid gap-1.5">
                    <Label>General appearance</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="a in appearances"
                            :key="a.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors disabled:opacity-60"
                            :class="
                                form.structured.examination.general
                                    .appearance === a.value
                                    ? 'border-primary bg-primary/10 font-medium text-foreground'
                                    : 'border-border text-muted-foreground hover:bg-muted'
                            "
                            :disabled="readOnly"
                            @click="
                                form.structured.examination.general.appearance =
                                    form.structured.examination.general
                                        .appearance === a.value
                                        ? ''
                                        : a.value
                            "
                        >
                            {{ a.label }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label>Level of consciousness</Label>
                        <Input
                            v-model="
                                form.structured.examination.general
                                    .consciousness
                            "
                            placeholder="e.g. Alert & oriented"
                            :disabled="readOnly"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Hydration</Label>
                        <Input
                            v-model="
                                form.structured.examination.general.hydration
                            "
                            placeholder="e.g. Well hydrated"
                            :disabled="readOnly"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Findings</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="t in generalToggles"
                            :key="t.key"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm transition-colors disabled:opacity-60"
                            :class="
                                form.structured.examination.general[t.key]
                                    ? 'border-amber-500/40 bg-amber-500/10 font-medium text-amber-700 dark:text-amber-400'
                                    : 'border-border text-muted-foreground hover:bg-muted'
                            "
                            :disabled="readOnly"
                            @click="
                                form.structured.examination.general[t.key] =
                                    !form.structured.examination.general[t.key]
                            "
                        >
                            <Check
                                v-if="
                                    form.structured.examination.general[t.key]
                                "
                                class="size-3.5"
                            />
                            {{ t.label }}
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Highlight a finding when it is present.
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 text-base font-semibold">Systemic examination</h2>
            <div class="divide-y divide-border rounded-md border border-border">
                <Collapsible
                    v-for="sys in examSystems"
                    :key="sys.key"
                    v-slot="{ open }"
                >
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm font-medium"
                    >
                        <span class="flex items-center gap-2">
                            {{ sys.label }}
                            <span
                                v-if="
                                    form.structured.examination.systems[sys.key]
                                "
                                class="size-1.5 rounded-full bg-primary"
                                aria-hidden
                            />
                        </span>
                        <ChevronDown
                            class="size-4 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': open }"
                        />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="px-3 pt-1 pb-3">
                        <textarea
                            v-model="
                                form.structured.examination.systems[sys.key]
                            "
                            :class="textareaClass"
                            rows="2"
                            :disabled="readOnly"
                            placeholder="Inspection · Palpation · Percussion · Auscultation…"
                            @input="autoGrow"
                        />
                    </CollapsibleContent>
                </Collapsible>
            </div>
            <div class="mt-4 grid gap-1.5">
                <Label>Examination summary (optional)</Label>
                <textarea
                    v-model="form.objective"
                    :class="textareaClass"
                    rows="2"
                    :disabled="readOnly"
                    placeholder="Overall examination summary…"
                    @input="autoGrow"
                />
                <InputError :message="form.errors.objective" />
            </div>
        </div>
    </section>
</template>
