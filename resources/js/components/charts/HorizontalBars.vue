<script setup lang="ts">
import { computed } from 'vue';
import { paletteColor } from './palette';

type Item = { label: string; value: number };

const props = withDefaults(
    defineProps<{
        items: Item[];
        formatValue?: (value: number) => string;
        colorful?: boolean;
        color?: string;
    }>(),
    { colorful: false, color: '#6366f1' },
);

const format = (v: number): string =>
    props.formatValue ? props.formatValue(v) : v.toLocaleString();

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
</script>

<template>
    <ul class="flex flex-col gap-2.5">
        <li
            v-for="(item, i) in items"
            :key="item.label"
            class="flex items-center gap-3 text-sm"
        >
            <span class="w-28 shrink-0 truncate text-muted-foreground" :title="item.label">{{
                item.label
            }}</span>
            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-[width] duration-500"
                    :style="{
                        width: `${(item.value / max) * 100}%`,
                        backgroundColor: colorful ? paletteColor(i) : color,
                    }"
                />
            </div>
            <span class="w-12 shrink-0 text-right font-medium tabular-nums">{{
                format(item.value)
            }}</span>
        </li>
        <li v-if="!items.length" class="py-4 text-center text-sm text-muted-foreground">
            No data in this period.
        </li>
    </ul>
</template>
