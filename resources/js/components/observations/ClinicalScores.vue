<script setup lang="ts">
import { computed } from 'vue';
import { bmiScore, news2Score } from '@/lib/observations';
import type { ClinicalScore } from '@/lib/observations';
import type { ObservationSet } from '@/types/clinical';

const props = defineProps<{ set: ObservationSet | null }>();

const scores = computed<ClinicalScore[]>(() =>
    [bmiScore(props.set), news2Score(props.set)].filter(
        (s): s is ClinicalScore => s !== null,
    ),
);

const levelClass = (level: string) => {
    if (level === 'critical') {
        return 'border-red-500/30 bg-red-500/5 text-red-700 dark:text-red-400';
    }

    if (level === 'warning') {
        return 'border-amber-500/30 bg-amber-500/5 text-amber-700 dark:text-amber-400';
    }

    return 'border-border bg-muted/40 text-foreground';
};

defineExpose({ scores });
</script>

<template>
    <div
        v-if="scores.length"
        class="rounded-xl border border-border bg-card p-5"
    >
        <h3 class="mb-3 text-sm font-semibold">Clinical scores</h3>
        <div class="grid grid-cols-2 gap-3">
            <div
                v-for="score in scores"
                :key="score.label"
                class="rounded-lg border px-3 py-2"
                :class="levelClass(score.level)"
            >
                <p
                    class="text-[11px] font-medium tracking-wide uppercase opacity-70"
                >
                    {{ score.label }}
                </p>
                <p class="text-lg leading-tight font-bold">{{ score.value }}</p>
                <p v-if="score.detail" class="text-[11px]">
                    {{ score.detail }}
                </p>
            </div>
        </div>
    </div>
</template>
