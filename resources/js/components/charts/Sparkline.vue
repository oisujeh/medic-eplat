<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useResizeWidth } from '@/composables/useResizeWidth';
import { CHART_PRIMARY } from './palette';

const props = withDefaults(
    defineProps<{
        values: number[];
        color?: string;
        height?: number;
    }>(),
    { color: CHART_PRIMARY, height: 40 },
);

const container = ref<HTMLElement | null>(null);
const width = useResizeWidth(container, 160);
const gradientId = `spark-${useId()}`;

const pad = 2;
const max = computed(() => Math.max(1, ...props.values));
const min = computed(() => Math.min(0, ...props.values));

const coords = computed(() => {
    const n = props.values.length;
    if (n === 0) {
        return [];
    }
    const span = max.value - min.value || 1;
    const usableW = width.value - pad * 2;
    const usableH = props.height - pad * 2;
    return props.values.map((v, i) => ({
        x: pad + (n > 1 ? (i / (n - 1)) * usableW : usableW / 2),
        y: pad + usableH * (1 - (v - min.value) / span),
    }));
});

const linePath = computed(() =>
    coords.value
        .map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)},${c.y.toFixed(1)}`)
        .join(' '),
);

const areaPath = computed(() => {
    if (coords.value.length < 2) {
        return '';
    }
    const first = coords.value[0];
    const last = coords.value[coords.value.length - 1];
    return `${linePath.value} L${last.x.toFixed(1)},${props.height - pad} L${first.x.toFixed(1)},${props.height - pad} Z`;
});
</script>

<template>
    <div ref="container" class="w-full">
        <svg :width="width" :height="height" class="block">
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.25" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path v-if="areaPath" :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                v-if="linePath"
                :d="linePath"
                fill="none"
                :stroke="color"
                stroke-width="1.75"
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
            />
        </svg>
    </div>
</template>
