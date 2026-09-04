<script setup lang="ts">
import { Check, Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import LabOrderPanel from '@/components/clinical-record/LabOrderPanel.vue';
import MedicationPanel from '@/components/clinical-record/MedicationPanel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    counselingOptions,
    imagingPresets,
    procedurePresets,
    referralPresets,
    useEncounterContext,
} from '@/composables/useEncounterForm';
import { autoGrow, textareaClass, toggleInList } from '@/lib/forms';
import type { LabResult, LabTest, Medication } from '@/types/clinical';

defineProps<{
    medications: Medication[];
    labResults: LabResult[];
    labCatalog: LabTest[];
}>();

const { encounter, form, readOnly } = useEncounterContext();

type PlanKind = 'procedures' | 'imaging' | 'referrals';

const kinds: Array<{ key: PlanKind; label: string; presets: string[] }> = [
    { key: 'procedures', label: 'Procedures', presets: procedurePresets },
    { key: 'imaging', label: 'Imaging', presets: imagingPresets },
    { key: 'referrals', label: 'Referrals', presets: referralPresets },
];

const custom = ref<Record<PlanKind, string>>({
    procedures: '',
    imaging: '',
    referrals: '',
});

function addCustom(kind: PlanKind) {
    const v = custom.value[kind].trim();

    if (v && !form.structured.plan[kind].includes(v)) {
        form.structured.plan[kind].push(v);
    }

    custom.value[kind] = '';
}

const labResultsBase = (url: string) =>
    url.replace(/\/lab-orders$/, '/lab-results');
</script>

<template>
    <section class="flex flex-col gap-4">
        <MedicationPanel
            :medications="medications"
            :action="encounter.urls.medications"
            :disabled="readOnly"
        />

        <LabOrderPanel
            :lab-results="labResults"
            :catalog="labCatalog"
            :order-action="encounter.urls.lab_orders"
            :results-base="labResultsBase(encounter.urls.lab_orders)"
            :disabled="readOnly"
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <div
                v-for="kind in kinds"
                :key="kind.key"
                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-4"
            >
                <h3 class="text-sm font-semibold">{{ kind.label }}</h3>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="preset in kind.presets"
                        :key="preset"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs transition-colors disabled:opacity-60"
                        :class="
                            form.structured.plan[kind.key].includes(preset)
                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                : 'border-border text-muted-foreground hover:bg-muted'
                        "
                        :disabled="readOnly"
                        @click="
                            toggleInList(form.structured.plan[kind.key], preset)
                        "
                    >
                        <Check
                            v-if="
                                form.structured.plan[kind.key].includes(preset)
                            "
                            class="size-3"
                        />
                        <Plus v-else class="size-3" />
                        {{ preset }}
                    </button>
                </div>
                <div
                    v-if="
                        form.structured.plan[kind.key].some(
                            (v) => !kind.presets.includes(v),
                        )
                    "
                    class="flex flex-wrap gap-1.5"
                >
                    <span
                        v-for="item in form.structured.plan[kind.key].filter(
                            (v) => !kind.presets.includes(v),
                        )"
                        :key="item"
                        class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ item }}
                        <button
                            v-if="!readOnly"
                            type="button"
                            aria-label="Remove"
                            @click="
                                toggleInList(
                                    form.structured.plan[kind.key],
                                    item,
                                )
                            "
                        >
                            <X class="size-3" />
                        </button>
                    </span>
                </div>
                <div v-if="!readOnly" class="flex gap-1.5">
                    <Input
                        v-model="custom[kind.key]"
                        placeholder="Add other…"
                        class="h-8 text-xs"
                        @keydown.enter.prevent="addCustom(kind.key)"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        aria-label="Add"
                        @click="addCustom(kind.key)"
                    >
                        <Plus class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h3 class="mb-3 text-sm font-semibold">Counseling</h3>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <label
                    v-for="c in counselingOptions"
                    :key="c.value"
                    class="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="
                            form.structured.plan.counseling.includes(c.value)
                        "
                        :disabled="readOnly"
                        @update:model-value="
                            toggleInList(
                                form.structured.plan.counseling,
                                c.value,
                            )
                        "
                    />
                    {{ c.label }}
                </label>
            </div>
            <div class="mt-4 grid gap-1.5">
                <Label>Management notes</Label>
                <textarea
                    v-model="form.plan"
                    :class="textareaClass"
                    rows="3"
                    :disabled="readOnly"
                    placeholder="Additional management plan / instructions…"
                    @input="autoGrow"
                />
                <InputError :message="form.errors.plan" />
            </div>
        </div>
    </section>
</template>
