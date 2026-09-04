<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/components/Sparkline.vue';
import { numeric } from '@/lib/observations';
import type { ObservationSet } from '@/types/clinical';

const props = withDefaults(
    defineProps<{
        /** Newest first, as the resources deliver them. */
        sets: ObservationSet[];
        codes?: Array<{ code: string; label: string; unit: string }>;
    }>(),
    {
        codes: () => [
            { code: 'temperature', label: 'Temperature', unit: '°C' },
            { code: 'pulse', label: 'Pulse', unit: 'bpm' },
            { code: 'spo2', label: 'SpO₂', unit: '%' },
            { code: 'systolic_bp', label: 'Systolic BP', unit: 'mmHg' },
            { code: 'respiratory_rate', label: 'Resp. rate', unit: '/min' },
            { code: 'weight', label: 'Weight', unit: 'kg' },
            { code: 'bmi', label: 'BMI', unit: '' },
            { code: 'blood_glucose', label: 'Glucose', unit: 'mmol/L' },
            { code: 'fundal_height', label: 'Fundal height', unit: 'cm' },
            {
                code: 'fetal_heart_rate',
                label: 'Fetal heart rate',
                unit: 'bpm',
            },
        ],
    },
);

// Oldest → newest so the sparkline reads left-to-right in time.
const chronological = computed(() => [...props.sets].reverse());

const series = computed(() =>
    props.codes
        .map((m) => {
            const points = chronological.value
                .map((s) => numeric(s, m.code))
                .filter((n): n is number => n !== null);
            const latest = points[points.length - 1] ?? null;

            return { ...m, points, latest };
        })
        .filter((s) => s.points.length >= 2),
);
</script>

<template>
    <div v-if="series.length" class="mt-4 border-t border-border pt-4">
        <p class="mb-3 text-xs font-semibold text-muted-foreground">
            Trends ({{ chronological.length }} sets)
        </p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="s in series"
                :key="s.code"
                class="rounded-lg border border-border p-3"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-xs text-muted-foreground">{{
                        s.label
                    }}</span>
                    <span class="text-sm font-semibold tabular-nums">
                        {{ s.latest
                        }}<span
                            class="text-xs font-normal text-muted-foreground"
                            >{{ s.unit ? ' ' + s.unit : '' }}</span
                        >
                    </span>
                </div>
                <Sparkline
                    :points="s.points"
                    :width="150"
                    :height="36"
                    class="mt-2 w-full"
                />
            </div>
        </div>
    </div>
</template>
