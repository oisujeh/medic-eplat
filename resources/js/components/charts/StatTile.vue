<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        delta?: number | null;
        invertDelta?: boolean;
        sub?: string | null;
        icon?: Component | null;
    }>(),
    { delta: null, invertDelta: false, sub: null, icon: null },
);

const hasDelta = computed(() => props.delta !== null && Number.isFinite(props.delta));

// Positive movement is good by default; for cost-like metrics, invert.
const isGood = computed(() => {
    if (props.delta === null || props.delta === 0) {
        return null;
    }
    const up = props.delta > 0;
    return props.invertDelta ? !up : up;
});

const toneClass = computed(() => {
    if (isGood.value === null) {
        return 'text-muted-foreground';
    }
    return isGood.value
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-red-600 dark:text-red-400';
});
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-4">
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm text-muted-foreground">{{ label }}</span>
            <span
                v-if="icon"
                class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <div class="mt-2 flex items-end justify-between gap-2">
            <span class="text-2xl font-semibold tracking-tight tabular-nums">{{
                value
            }}</span>
            <span
                v-if="hasDelta"
                class="mb-0.5 inline-flex items-center gap-0.5 text-xs font-medium"
                :class="toneClass"
            >
                <component
                    :is="delta! >= 0 ? ArrowUpRight : ArrowDownRight"
                    class="size-3.5"
                />
                {{ Math.abs(delta!) }}%
            </span>
        </div>
        <p v-if="sub" class="mt-1 text-xs text-muted-foreground">{{ sub }}</p>
    </div>
</template>
