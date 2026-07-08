<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        points: number[];
        width?: number;
        height?: number;
    }>(),
    { width: 150, height: 40 },
);

const PAD = 4;

// Scale the raw values into SVG coordinates (y inverted so higher = up).
const coords = computed(() => {
    const n = props.points.length;
    if (n === 0) return [];
    const min = Math.min(...props.points);
    const max = Math.max(...props.points);
    const range = max - min || 1;
    const innerW = props.width - PAD * 2;
    const innerH = props.height - PAD * 2;

    return props.points.map((v, i) => {
        const x = n === 1 ? props.width / 2 : PAD + (i / (n - 1)) * innerW;
        const y = PAD + innerH - ((v - min) / range) * innerH;
        return { x, y };
    });
});

const line = computed(() =>
    coords.value.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' '),
);

const area = computed(() => {
    if (!coords.value.length) return '';
    const base = props.height - PAD;
    const first = coords.value[0];
    const last = coords.value[coords.value.length - 1];
    const path = coords.value
        .map((c, i) => `${i === 0 ? 'M' : 'L'} ${c.x.toFixed(1)} ${c.y.toFixed(1)}`)
        .join(' ');
    return `${path} L ${last.x.toFixed(1)} ${base} L ${first.x.toFixed(1)} ${base} Z`;
});

const lastPoint = computed(() => coords.value[coords.value.length - 1] ?? null);
</script>

<template>
    <svg
        :width="width"
        :height="height"
        :viewBox="`0 0 ${width} ${height}`"
        class="text-primary"
        preserveAspectRatio="none"
        role="img"
        aria-label="Trend sparkline"
    >
        <path :d="area" fill="currentColor" opacity="0.08" />
        <polyline
            :points="line"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
        <circle
            v-if="lastPoint"
            :cx="lastPoint.x"
            :cy="lastPoint.y"
            r="2.5"
            fill="currentColor"
        />
    </svg>
</template>
