<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useResizeWidth } from '@/composables/useResizeWidth';
import { CHART_PRIMARY } from './palette';

type Point = { label: string; value: number };

const props = withDefaults(
    defineProps<{
        points: Point[];
        height?: number;
        color?: string;
        formatValue?: (value: number) => string;
    }>(),
    { height: 220, color: CHART_PRIMARY },
);

const container = ref<HTMLElement | null>(null);
const width = useResizeWidth(container);
const gradientId = `line-grad-${useId()}`;

const format = (v: number): string =>
    props.formatValue ? props.formatValue(v) : v.toLocaleString();

const padTop = 16;
const padBottom = 10;
const padX = 6;

const maxValue = computed(() =>
    Math.max(1, ...props.points.map((p) => p.value)),
);

const plotHeight = computed(() => props.height - padTop - padBottom);
const baseY = computed(() => padTop + plotHeight.value);

const step = computed(() => {
    const n = props.points.length;
    const usable = width.value - padX * 2;
    return n > 1 ? usable / (n - 1) : 0;
});

const x = (i: number): number =>
    props.points.length > 1 ? padX + i * step.value : width.value / 2;

const y = (v: number): number =>
    padTop + plotHeight.value * (1 - v / maxValue.value);

const coords = computed(() =>
    props.points.map((p, i) => ({
        x: x(i),
        y: y(p.value),
        value: p.value,
        label: p.label,
    })),
);

const linePath = computed(() =>
    coords.value
        .map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)},${c.y.toFixed(1)}`)
        .join(' '),
);

const areaPath = computed(() => {
    if (!coords.value.length) {
        return '';
    }
    const first = coords.value[0];
    const last = coords.value[coords.value.length - 1];
    return `${linePath.value} L${last.x.toFixed(1)},${baseY.value} L${first.x.toFixed(1)},${baseY.value} Z`;
});

const gridlines = computed(() =>
    [0, 0.5, 1].map((f) => ({
        y: padTop + plotHeight.value * (1 - f),
        value: Math.round(maxValue.value * f),
    })),
);

const showDots = computed(() => props.points.length <= 31);

// X-axis labels: show at most ~6 evenly spaced ticks to avoid crowding.
const xLabels = computed(() => {
    const n = props.points.length;
    if (!n) {
        return [];
    }
    const maxTicks = 6;
    const stride = Math.max(1, Math.ceil(n / maxTicks));
    return props.points
        .map((p, i) => ({ label: p.label, i }))
        .filter(({ i }) => i % stride === 0 || i === n - 1);
});

const hoverIndex = ref<number | null>(null);
const active = computed(() =>
    hoverIndex.value !== null ? coords.value[hoverIndex.value] : null,
);

function onMove(event: PointerEvent) {
    const rect = container.value?.getBoundingClientRect();
    if (!rect || props.points.length === 0) {
        return;
    }
    const mx = event.clientX - rect.left;
    const i = step.value > 0 ? Math.round((mx - padX) / step.value) : 0;
    hoverIndex.value = Math.min(props.points.length - 1, Math.max(0, i));
}
</script>

<template>
    <div ref="container" class="relative w-full" @pointerleave="hoverIndex = null">
        <svg
            :width="width"
            :height="height"
            class="block overflow-visible"
            @pointermove="onMove"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.28" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <!-- gridlines -->
            <line
                v-for="(g, i) in gridlines"
                :key="i"
                :x1="padX"
                :x2="width - padX"
                :y1="g.y"
                :y2="g.y"
                class="stroke-border"
                stroke-dasharray="3 4"
                stroke-width="1"
            />

            <path v-if="areaPath" :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                v-if="linePath"
                :d="linePath"
                fill="none"
                :stroke="color"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
            />

            <!-- static dots -->
            <template v-if="showDots">
                <circle
                    v-for="(c, i) in coords"
                    :key="i"
                    :cx="c.x"
                    :cy="c.y"
                    r="2.5"
                    :fill="color"
                />
            </template>

            <!-- hover guide -->
            <g v-if="active">
                <line
                    :x1="active.x"
                    :x2="active.x"
                    :y1="padTop"
                    :y2="baseY"
                    :stroke="color"
                    stroke-width="1"
                    stroke-opacity="0.4"
                />
                <circle
                    :cx="active.x"
                    :cy="active.y"
                    r="4"
                    :fill="color"
                    class="stroke-background"
                    stroke-width="2"
                />
            </g>
        </svg>

        <!-- y-axis value labels -->
        <div class="pointer-events-none absolute inset-0">
            <span
                v-for="(g, i) in gridlines"
                :key="i"
                class="absolute left-0 -translate-y-1/2 bg-card/70 pr-1 text-[10px] tabular-nums text-muted-foreground"
                :style="{ top: `${g.y}px` }"
                >{{ format(g.value) }}</span
            >
        </div>

        <!-- hover tooltip -->
        <div
            v-if="active"
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-md border border-border bg-popover px-2 py-1 text-xs shadow-md"
            :style="{ left: `${active.x}px`, top: `${active.y - 8}px` }"
        >
            <div class="font-medium text-popover-foreground">
                {{ format(active.value) }}
            </div>
            <div class="text-muted-foreground">{{ active.label }}</div>
        </div>

        <!-- x-axis labels -->
        <div class="relative mt-1 h-4">
            <span
                v-for="t in xLabels"
                :key="t.i"
                class="absolute -translate-x-1/2 text-[10px] whitespace-nowrap text-muted-foreground"
                :style="{ left: `${x(t.i)}px` }"
                >{{ t.label }}</span
            >
        </div>
    </div>
</template>
