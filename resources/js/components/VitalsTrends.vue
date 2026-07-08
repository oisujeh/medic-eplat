<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/components/Sparkline.vue';
import type { Vitals } from '@/lib/vitals';

const props = defineProps<{ vitals: Vitals[] }>();

type Metric = { key: keyof Vitals; label: string; unit: string };

const METRICS: Metric[] = [
    { key: 'temperature_c', label: 'Temperature', unit: '°C' },
    { key: 'pulse_bpm', label: 'Pulse', unit: 'bpm' },
    { key: 'spo2', label: 'SpO₂', unit: '%' },
    { key: 'systolic_bp', label: 'Systolic BP', unit: 'mmHg' },
    { key: 'respiratory_rate', label: 'Resp. rate', unit: '/min' },
    { key: 'weight_kg', label: 'Weight', unit: 'kg' },
    { key: 'bmi', label: 'BMI', unit: '' },
    { key: 'blood_glucose', label: 'Glucose', unit: 'mmol/L' },
];

// Oldest → newest so the sparkline reads left-to-right in time.
const chronological = computed(() => [...props.vitals].reverse());

const series = computed(() =>
    METRICS.map((m) => {
        const points = chronological.value
            .map((v) => v[m.key])
            .filter((n): n is number => typeof n === 'number');
        const latest = points[points.length - 1] ?? null;
        return { ...m, points, latest };
    }).filter((s) => s.points.length >= 2),
);
</script>

<template>
    <div v-if="series.length" class="mt-4 border-t border-border pt-4">
        <p class="mb-3 text-xs font-semibold text-muted-foreground">
            Trends ({{ chronological.length }} readings)
        </p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="s in series"
                :key="s.key"
                class="rounded-lg border border-border p-3"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-xs text-muted-foreground">{{
                        s.label
                    }}</span>
                    <span class="text-sm font-semibold">
                        {{ s.latest }}<span class="text-xs font-normal text-muted-foreground">{{ s.unit ? ' ' + s.unit : '' }}</span>
                    </span>
                </div>
                <Sparkline :points="s.points" :width="150" :height="36" class="mt-2 w-full" />
            </div>
        </div>
    </div>
</template>
