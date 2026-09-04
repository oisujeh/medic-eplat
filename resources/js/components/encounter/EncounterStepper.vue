<script setup lang="ts">
import { History } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

export type Stage = { key: string; label: string };

const props = defineProps<{
    stages: Stage[];
    modelValue: string;
    /** Number of past encounters, shown on the History button. */
    historyCount?: number;
}>();

const emit = defineEmits<{ 'update:modelValue': [key: string] }>();

const index = computed(() =>
    props.stages.findIndex((s) => s.key === props.modelValue),
);
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-b border-border"
    >
        <nav class="flex flex-wrap items-center gap-x-1 gap-y-2">
            <button
                v-for="(s, i) in stages"
                :key="s.key"
                type="button"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 pt-1 pb-2.5 text-sm font-medium transition-colors"
                :class="
                    modelValue === s.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="emit('update:modelValue', s.key)"
            >
                <span
                    v-if="stages.length > 1"
                    class="flex size-5 items-center justify-center rounded-full text-xs font-semibold"
                    :class="
                        modelValue === s.key
                            ? 'bg-primary text-primary-foreground'
                            : i < index
                              ? 'bg-primary/15 text-primary'
                              : 'bg-muted text-muted-foreground'
                    "
                    >{{ i + 1 }}</span
                >
                {{ s.label }}
            </button>
        </nav>
        <div class="-mb-px flex items-center gap-1 pb-1">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :class="
                    modelValue === 'history'
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
                @click="emit('update:modelValue', 'history')"
            >
                <History class="size-4" />
                History
                <span
                    v-if="historyCount"
                    class="rounded-full bg-muted px-1.5 text-[11px] font-semibold"
                    >{{ historyCount }}</span
                >
            </Button>
        </div>
    </div>
</template>
