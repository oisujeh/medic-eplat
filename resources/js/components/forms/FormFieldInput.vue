<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import ConceptPicker from '@/components/clinical-record/ConceptPicker.vue';
import IcdPicker from '@/components/clinical-record/IcdPicker.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormFill } from '@/composables/useFormFill';
import type { IcdMatch } from '@/composables/useIcdSearch';
import { labelOf } from '@/lib/formEngine';
import { textareaClass } from '@/lib/forms';
import type {
    DiagnosisAnswer,
    DrugAnswer,
    FormField,
    FormInstance,
    LabTestAnswer,
} from '@/types/forms';

/**
 * One field of a form, rendered by its type. Reads and writes the shared
 * answers; `scope` is the repeat instance the field sits in, if any.
 */
const props = defineProps<{
    field: FormField;
    scope: FormInstance | null;
    /** The error path: "weight" or "specimens.1.site". */
    path: string;
    /** Rapid entry: inline label, tighter spacing. */
    compact?: boolean;
}>();

const fill = useFormFill();

const id = computed(() => `f-${props.path.replace(/\./g, '-')}`);
const label = computed(() => labelOf(props.field, fill.locale));
const help = computed(() => labelOf(props.field, fill.locale, 'help'));
const placeholder = computed(() =>
    props.field.placeholder
        ? labelOf(props.field, fill.locale, 'placeholder')
        : '',
);
const error = computed(() => fill.errorFor(props.path));
const disabled = computed(() => fill.readonly.value);

const value = computed(() =>
    props.scope ? props.scope[props.field.key] : fill.data[props.field.key],
);

function set(next: unknown) {
    fill.setValue(props.scope, props.field.key, next);
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-input/30';

const segmentClass = (active: boolean) =>
    [
        'rounded-md border px-3 py-1.5 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60',
        active
            ? 'border-primary bg-primary text-primary-foreground'
            : 'border-input bg-transparent hover:bg-muted',
    ].join(' ');

// Coded choices: a few options render as buttons, more as a list.
const asButtons = computed(
    () =>
        props.field.type === 'coded' &&
        !props.field.multiple &&
        (props.field.options?.length ?? 0) <= 4,
);

function toggleMulti(option: string) {
    const list = Array.isArray(value.value)
        ? [...(value.value as string[])]
        : [];
    const i = list.indexOf(option);

    if (i === -1) {
        list.push(option);
    } else {
        list.splice(i, 1);
    }

    set(list);
}

function setBoolean(next: boolean) {
    set(value.value === next ? null : next);
}

// Catalogue answers.
const drug = computed(() => (value.value as DrugAnswer | null) ?? null);
const diagnosis = computed(
    () => (value.value as DiagnosisAnswer | null) ?? null,
);
const labTest = computed(() => (value.value as LabTestAnswer | null) ?? null);

function setDrugName(name: string) {
    set(name ? { id: drug.value?.id ?? null, name } : null);
}

function setDrugId(conceptId: number | null) {
    set(
        conceptId
            ? { id: conceptId, name: drug.value?.name ?? '' }
            : drug.value?.name
              ? { id: null, name: drug.value.name }
              : null,
    );
}

function setDiagnosisText(text: string) {
    if (!text) {
        set(null);
    } else if (diagnosis.value && diagnosis.value.description !== text) {
        set({ code: '', description: text });
    }
}

function pickDiagnosis(match: IcdMatch) {
    set({ code: match.code, description: match.description });
}

function pickLabTest(event: Event) {
    const idValue = Number((event.target as HTMLSelectElement).value);
    const test = fill.labTests.find((t) => t.id === idValue);

    set(test ? { id: test.id, name: test.name } : null);
}

const calculatedDisplay = computed(() => {
    const v = value.value;

    if (v === null || v === undefined || v === '') {
        return '—';
    }

    return `${v}${props.field.unit ? ` ${props.field.unit}` : ''}`;
});
</script>

<template>
    <div
        :class="
            compact
                ? 'grid grid-cols-[minmax(8rem,14rem)_1fr] items-start gap-x-3 gap-y-1'
                : 'flex flex-col gap-1.5'
        "
        :data-field="field.key"
    >
        <Label
            :for="id"
            :class="compact ? 'pt-2 text-sm leading-tight' : 'text-sm'"
        >
            {{ label }}
            <span
                v-if="field.required"
                class="text-destructive"
                aria-hidden="true"
                >*</span
            >
        </Label>

        <div class="flex min-w-0 flex-col gap-1">
            <!-- Number -->
            <div
                v-if="field.type === 'numeric'"
                class="flex items-center gap-2"
            >
                <Input
                    :id="id"
                    type="number"
                    inputmode="decimal"
                    :model-value="(value as string | number | null) ?? ''"
                    :min="field.min"
                    :max="field.max"
                    :step="field.step ?? (field.integer ? 1 : 'any')"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :aria-invalid="!!error"
                    class="max-w-48"
                    @update:model-value="(v) => set(v === '' ? null : v)"
                />
                <span v-if="field.unit" class="text-sm text-muted-foreground">{{
                    field.unit
                }}</span>
            </div>

            <!-- Text -->
            <textarea
                v-else-if="field.type === 'text' && (field.rows ?? 1) > 1"
                :id="id"
                :rows="field.rows"
                :value="(value as string | null) ?? ''"
                :maxlength="field.max_length"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="!!error"
                :class="[textareaClass, 'resize-y overflow-auto']"
                @input="
                    set(($event.target as HTMLTextAreaElement).value || null)
                "
            />
            <Input
                v-else-if="field.type === 'text'"
                :id="id"
                type="text"
                :model-value="(value as string | null) ?? ''"
                :maxlength="field.max_length"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="!!error"
                @update:model-value="(v) => set(v === '' ? null : v)"
            />

            <!-- Coded, single -->
            <div
                v-else-if="field.type === 'coded' && asButtons"
                class="flex flex-wrap gap-1.5"
                role="radiogroup"
                :aria-labelledby="id"
            >
                <button
                    v-for="option in field.options"
                    :key="String(option.value)"
                    type="button"
                    role="radio"
                    :aria-checked="String(value) === String(option.value)"
                    :class="
                        segmentClass(String(value) === String(option.value))
                    "
                    :disabled="disabled"
                    @click="
                        set(
                            String(value) === String(option.value)
                                ? null
                                : String(option.value),
                        )
                    "
                >
                    {{ labelOf(option, fill.locale) }}
                </button>
            </div>
            <select
                v-else-if="field.type === 'coded' && !field.multiple"
                :id="id"
                :value="(value as string | null) ?? ''"
                :disabled="disabled"
                :aria-invalid="!!error"
                :class="selectClass"
                @change="
                    set(($event.target as HTMLSelectElement).value || null)
                "
            >
                <option value="">—</option>
                <option
                    v-for="option in field.options"
                    :key="String(option.value)"
                    :value="String(option.value)"
                >
                    {{ labelOf(option, fill.locale) }}
                </option>
            </select>

            <!-- Coded, multiple -->
            <div
                v-else-if="field.type === 'coded'"
                class="flex flex-wrap gap-x-4 gap-y-2"
                role="group"
                :aria-labelledby="id"
            >
                <label
                    v-for="option in field.options"
                    :key="String(option.value)"
                    class="inline-flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="
                            Array.isArray(value) &&
                            (value as string[]).includes(String(option.value))
                        "
                        :disabled="disabled"
                        @update:model-value="toggleMulti(String(option.value))"
                    />
                    {{ labelOf(option, fill.locale) }}
                </label>
            </div>

            <!-- Date, date-time -->
            <Input
                v-else-if="field.type === 'date'"
                :id="id"
                type="date"
                :model-value="(value as string | null) ?? ''"
                :disabled="disabled"
                :aria-invalid="!!error"
                class="max-w-52"
                @update:model-value="(v) => set(v === '' ? null : v)"
            />
            <Input
                v-else-if="field.type === 'datetime'"
                :id="id"
                type="datetime-local"
                :model-value="(value as string | null) ?? ''"
                :disabled="disabled"
                :aria-invalid="!!error"
                class="max-w-64"
                @update:model-value="(v) => set(v === '' ? null : v)"
            />

            <!-- Yes / No -->
            <div
                v-else-if="field.type === 'boolean'"
                class="flex gap-1.5"
                role="radiogroup"
                :aria-labelledby="id"
            >
                <button
                    type="button"
                    role="radio"
                    :aria-checked="value === true"
                    :class="segmentClass(value === true)"
                    :disabled="disabled"
                    @click="setBoolean(true)"
                >
                    Yes
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="value === false"
                    :class="segmentClass(value === false)"
                    :disabled="disabled"
                    @click="setBoolean(false)"
                >
                    No
                </button>
            </div>

            <!-- Drug -->
            <ConceptPicker
                v-else-if="field.type === 'drug'"
                :model-value="drug?.name ?? ''"
                :concept-id="drug?.id ?? null"
                concept-class="drug"
                :placeholder="placeholder || 'Search the drug dictionary'"
                :disabled="disabled"
                @update:model-value="setDrugName"
                @update:concept-id="setDrugId"
            />

            <!-- Diagnosis -->
            <div
                v-else-if="field.type === 'diagnosis'"
                class="flex items-center gap-2"
            >
                <IcdPicker
                    :model-value="diagnosis?.description ?? ''"
                    :disabled="disabled"
                    @update:model-value="setDiagnosisText"
                    @pick="pickDiagnosis"
                />
                <span
                    v-if="diagnosis?.code"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 font-mono text-[11px]"
                >
                    <Check class="size-3 text-emerald-600" />
                    {{ diagnosis.code }}
                </span>
            </div>

            <!-- Laboratory test -->
            <select
                v-else-if="field.type === 'lab_test'"
                :id="id"
                :value="labTest?.id ?? ''"
                :disabled="disabled"
                :aria-invalid="!!error"
                :class="selectClass"
                @change="pickLabTest"
            >
                <option value="">—</option>
                <option v-for="t in fill.labTests" :key="t.id" :value="t.id">
                    {{ t.code }} · {{ t.name }}
                </option>
            </select>

            <!-- Calculated -->
            <output
                v-else-if="field.type === 'calculated'"
                :id="id"
                class="inline-flex h-9 items-center rounded-md border border-dashed border-input px-3 text-sm text-muted-foreground tabular-nums"
            >
                {{ calculatedDisplay }}
            </output>

            <p v-if="help && !error" class="text-xs text-muted-foreground">
                {{ help }}
            </p>
            <p v-if="error" class="text-xs text-destructive" role="alert">
                {{ error }}
            </p>
        </div>
    </div>
</template>
