<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ObservationCodeDefinition } from '@/types/clinical';

/**
 * The one form for recording a set of readings, driven by the code
 * definitions the backend serves. Used from the queue, an encounter, and the
 * ward.
 */
const props = withDefaults(
    defineProps<{
        /** patients.observations.store for the patient. */
        action: string;
        codes: ObservationCodeDefinition[];
        /** Panels to capture, in order. */
        panels?: string[];
        /** Links the set to its clinical context. */
        context?: {
            queue_entry_id?: number;
            encounter_id?: number;
            admission_id?: number;
        };
        /** Tighter inputs for inline use. */
        compact?: boolean;
    }>(),
    {
        panels: () => ['vitals', 'anthropometrics'],
        context: () => ({}),
        compact: false,
    },
);

const emit = defineEmits<{ saved: []; cancel: [] }>();

const enterable = computed(() =>
    props.codes.filter((c) => !c.derived && props.panels.includes(c.panel)),
);

const panelGroups = computed(() =>
    props.panels
        .map((panel) => ({
            panel,
            label:
                {
                    vitals: 'Vitals',
                    anthropometrics: 'Anthropometrics',
                    antenatal: 'Antenatal',
                }[panel] ?? panel,
            codes: enterable.value.filter((c) => c.panel === panel),
        }))
        .filter((g) => g.codes.length),
);

const blank = (): Record<string, string> =>
    Object.fromEntries([
        ...enterable.value.map((c) => [c.value, '']),
        ['notes', ''],
    ]);

const form = useForm<Record<string, string>>(blank());

// Live BMI preview from the entered weight and height.
const bmiPreview = computed<string | null>(() => {
    const w = parseFloat(form.weight ?? '');
    const h = parseFloat(form.height ?? '');

    if (!w || !h) {
        return null;
    }

    const m = h / 100;

    return (w / (m * m)).toFixed(1);
});

const showsBmi = computed(
    () =>
        enterable.value.some((c) => c.value === 'weight') &&
        enterable.value.some((c) => c.value === 'height'),
);

const firstError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    const key = Object.keys(errors).find((k) => errors[k]);

    return key ? errors[key] : undefined;
});

function submit() {
    form.transform((data) => ({
        ...Object.fromEntries(
            Object.entries(data).map(([k, v]) => [k, v === '' ? null : v]),
        ),
        ...props.context,
    })).post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
        onFinish: () => form.transform((d) => d),
    });
}

const inputClass = computed(() =>
    props.compact ? 'h-8 bg-background' : 'bg-background',
);
</script>

<template>
    <form class="flex flex-col gap-3" @submit.prevent="submit">
        <div v-for="group in panelGroups" :key="group.panel">
            <p class="mb-2 text-xs font-semibold text-muted-foreground">
                {{ group.label }}
            </p>
            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <div v-for="c in group.codes" :key="c.value" class="grid gap-1">
                    <Label class="text-xs" :for="`obs-${c.value}`"
                        >{{ c.label
                        }}<span v-if="c.unit" class="text-muted-foreground">
                            ({{ c.unit }})</span
                        ></Label
                    >
                    <Input
                        :id="`obs-${c.value}`"
                        v-model="form[c.value]"
                        :type="c.text ? 'text' : 'number'"
                        :step="c.text ? undefined : c.step"
                        :min="c.text ? undefined : (c.min ?? undefined)"
                        :max="c.text ? undefined : (c.max ?? undefined)"
                        :class="inputClass"
                    />
                    <InputError :message="form.errors[c.value]" />
                </div>
                <div
                    v-if="group.panel === 'anthropometrics' && showsBmi"
                    class="grid gap-1"
                >
                    <Label class="text-xs">BMI (auto)</Label>
                    <div
                        class="flex items-center rounded-md border border-border bg-background px-3 text-sm"
                        :class="[
                            compact ? 'h-8' : 'h-9',
                            bmiPreview ? '' : 'text-muted-foreground',
                        ]"
                    >
                        {{ bmiPreview ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-1">
            <Label class="text-xs" for="obs-notes">Notes</Label>
            <Input
                id="obs-notes"
                v-model="form.notes"
                placeholder="Optional observations"
                :class="inputClass"
            />
        </div>

        <InputError :message="firstError" />

        <div class="flex gap-2">
            <Button type="submit" size="sm" :disabled="form.processing">
                <Check class="size-4" />
                Save observations
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('cancel')"
            >
                Dismiss
            </Button>
        </div>
    </form>
</template>
