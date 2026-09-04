<script setup lang="ts">
import { computed, ref } from 'vue';
import { useResizeWidth } from '@/composables/useResizeWidth';
import { CHART_PRIMARY } from './palette';

type Bar = { label: string; value: number };

const props = withDefaults(
    defineProps<{
        bars: Bar[];
        height?: number;
        color?: string;
        formatValue?: (value: number) => string;
    }>(),
    { height: 220, color: CHART_PRIMARY },
);

const container = ref<HTMLElement | null>(null);
const width = useResizeWidth(container);

const format = (v: number): string =>
    props.formatValue ? props.formatValue(v) : v.toLocaleString();

const padTop = 16;
const padBottom = 10;
const padX = 6;

const maxValue = computed(() => Math.max(1, ...props.bars.map((b) => b.value)));
const plotHeight = computed(() => props.height - padTop - padBottom);
const baseY = computed(() => padTop + plotHeight.value);

const slot = computed(() => {
    const n = props.bars.length || 1;
    return (width.value - padX * 2) / n;
});

const barWidth = computed(() => Math.max(2, Math.min(46, slot.value * 0.62)));

const geom = computed(() =>
    props.bars.map((b, i) => {
        const cx = padX + slot.value * (i + 0.5);
        const h = plotHeight.value * (b.value / maxValue.value);
        return {
            label: b.label,
            value: b.value,
            x: cx - barWidth.value / 2,
            cx,
            y: baseY.value - h,
            height: h,
        };
    }),
);

const gridlines = computed(() =>
    [0, 0.5, 1].map((f) => ({
        y: padTop + plotHeight.value * (1 - f),
        value: Math.round(maxValue.value * f),
    })),
);

const xLabels = computed(() => {
    const n = props.bars.length;
    const maxTicks = 8;
    const stride = Math.max(1, Math.ceil(n / maxTicks));
    return props.bars
        .map((b, i) => ({ label: b.label, i }))
        .filter(({ i }) => i % stride === 0 || i === n - 1);
});

const hoverIndex = ref<number | null>(null);
const active = computed(() =>
    hoverIndex.value !== null ? geom.value[hoverIndex.value] : null,
);
</script>

<template>
    <div ref="container" class="relative w-full" @pointerleave="hoverIndex = null">
        <svg :width="width" :height="height" class="block overflow-visible">
            <!-- gridlines -->
            <line
                v-for="(g, i) in gridlines"
                :key="`g${i}`"
                :x1="padX"
                :x2="width - padX"
                :y1="g.y"
                :y2="g.y"
                class="stroke-border"
                stroke-dasharray="3 4"
                stroke-width="1"
            />

            <!-- bars -->
            <rect
                v-for="(b, i) in geom"
                :key="i"
                :x="b.x"
                :y="b.y"
                :width="barWidth"
                :height="Math.max(0, b.height)"
                rx="3"
                :fill="color"
                :fill-opacity="hoverIndex === null || hoverIndex === i ? 0.9 : 0.35"
                @pointerenter="hoverIndex = i"
            />
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
            :style="{ left: `${active.cx}px`, top: `${active.y - 6}px` }"
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
                :style="{ left: `${padX + slot * (t.i + 0.5)}px` }"
                >{{ t.label }}</span
            >
        </div>
    </div>
</template>
