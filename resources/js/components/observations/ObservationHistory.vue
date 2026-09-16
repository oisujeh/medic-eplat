<script setup lang="ts">
import ObservationChips from '@/components/observations/ObservationChips.vue';
import type { ObservationSet } from '@/types/clinical';

defineProps<{
    /** Newest first. */
    sets: ObservationSet[];
    emptyText?: string;
}>();
</script>

<template>
    <p v-if="!sets.length" class="text-sm text-muted-foreground">
        {{ emptyText ?? 'No observations recorded.' }}
    </p>
    <ol v-else class="divide-y divide-border">
        <li v-for="s in sets" :key="s.id" class="py-3 first:pt-0 last:pb-0">
            <div
                class="mb-1.5 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
            >
                <span
                    >{{ s.recorded_at_short }} ·
                    {{ s.recorded_by ?? '—' }}</span
                >
            </div>
            <ObservationChips :set="s" />
            <p v-if="s.notes" class="mt-1.5 text-xs text-muted-foreground">
                {{ s.notes }}
            </p>
        </li>
    </ol>
</template>
