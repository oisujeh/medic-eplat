<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { alertBadge, chipClass } from '@/lib/observations';
import type { ObservationSet } from '@/types/clinical';

const props = withDefaults(
    defineProps<{
        set: ObservationSet;
        /** Show the overall Review / Critical badge with its flags. */
        badge?: boolean;
        /** Chip size. */
        size?: 'xs' | 'sm';
        /** Only show readings from these panels. */
        panels?: string[];
    }>(),
    { badge: true, size: 'xs', panels: undefined },
);

const readings = computed(() =>
    props.panels
        ? props.set.readings.filter((r) => props.panels!.includes(r.panel))
        : props.set.readings,
);

const alert = computed(() => alertBadge(props.set.alert_level));

// Systolic and diastolic can both flag "Hypertension"; say it once.
const alertText = computed(() => {
    const labels = [...new Set(props.set.flags.map((f) => f.label))];

    return labels.length
        ? `${alert.value?.label}: ${labels.join(', ')}`
        : (alert.value?.label ?? '');
});

const chip = computed(() =>
    props.size === 'sm'
        ? 'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm'
        : 'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs',
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div
            v-if="props.badge && alert"
            class="inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="alert.class"
        >
            <TriangleAlert class="size-3" />
            {{ alertText }}
        </div>
        <div class="flex flex-wrap gap-1.5">
            <span
                v-for="r in readings"
                :key="r.code"
                :class="[chip, chipClass(r.level)]"
                :title="r.flag ?? r.label"
            >
                <span
                    :class="
                        r.level === 'normal'
                            ? 'text-muted-foreground'
                            : 'opacity-80'
                    "
                    >{{ r.short_label }}</span
                >
                <span class="font-medium">{{ r.display }}</span>
            </span>
        </div>
    </div>
</template>
