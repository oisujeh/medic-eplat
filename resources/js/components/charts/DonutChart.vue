<script setup lang="ts">
import { computed, ref } from 'vue';
import { paletteColor } from './palette';

type Segment = { label: string; value: number };

const props = withDefaults(
    defineProps<{
        segments: Segment[];
        size?: number;
        thickness?: number;
        centerLabel?: string;
        formatValue?: (value: number) => string;
    }>(),
    { size: 172, thickness: 20 },
);

const format = (v: number): string =>
    props.formatValue ? props.formatValue(v) : v.toLocaleString();

const total = computed(() => props.segments.reduce((s, x) => s + x.value, 0));
const radius = computed(() => (props.size - props.thickness) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);

const arcs = computed(() => {
    let acc = 0;
    return props.segments.map((seg, i) => {
        const fraction = total.value > 0 ? seg.value / total.value : 0;
        const length = fraction * circumference.value;
        const arc = {
            color: paletteColor(i),
            label: seg.label,
            value: seg.value,
            percent: Math.round(fraction * 100),
            dash: `${length} ${circumference.value - length}`,
            offset: -acc,
        };
        acc += length;
        return arc;
    });
});

const hoverIndex = ref<number | null>(null);
const center = computed(() => props.size / 2);
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-6 gap-y-4">
        <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg :width="size" :height="size" class="block -rotate-90">
                <!-- track -->
                <circle
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    class="stroke-muted"
                    :stroke-width="thickness"
                />
                <circle
                    v-for="(a, i) in arcs"
                    :key="i"
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    :stroke="a.color"
                    :stroke-width="hoverIndex === i ? thickness + 3 : thickness"
                    :stroke-dasharray="a.dash"
                    :stroke-dashoffset="a.offset"
                    stroke-linecap="butt"
                    class="transition-[stroke-width] duration-150"
                    @pointerenter="hoverIndex = i"
                    @pointerleave="hoverIndex = null"
                />
            </svg>
            <!-- center total -->
            <div
                class="absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-lg font-semibold tabular-nums">{{
                    format(hoverIndex !== null ? arcs[hoverIndex].value : total)
                }}</span>
                <span class="max-w-[80%] truncate text-[11px] text-muted-foreground">{{
                    hoverIndex !== null ? arcs[hoverIndex].label : (centerLabel ?? 'Total')
                }}</span>
            </div>
        </div>

        <!-- legend -->
        <ul class="flex min-w-0 flex-1 flex-col gap-1.5">
            <li
                v-for="(a, i) in arcs"
                :key="i"
                class="flex items-center gap-2 text-sm"
                :class="hoverIndex !== null && hoverIndex !== i ? 'opacity-50' : ''"
                @pointerenter="hoverIndex = i"
                @pointerleave="hoverIndex = null"
            >
                <span
                    class="size-2.5 shrink-0 rounded-sm"
                    :style="{ backgroundColor: a.color }"
                />
                <span class="min-w-0 flex-1 truncate text-muted-foreground">{{
                    a.label
                }}</span>
                <span class="shrink-0 font-medium tabular-nums">{{
                    format(a.value)
                }}</span>
                <span
                    class="w-9 shrink-0 text-right text-xs tabular-nums text-muted-foreground"
                    >{{ a.percent }}%</span
                >
            </li>
            <li
                v-if="!segments.length"
                class="text-sm text-muted-foreground"
            >
                No data in this period.
            </li>
        </ul>
    </div>
</template>
