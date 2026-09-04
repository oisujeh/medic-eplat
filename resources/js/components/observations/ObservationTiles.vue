<script setup lang="ts">
import { computed } from 'vue';
import { levelTextClass } from '@/lib/observations';
import type { ObservationSet } from '@/types/clinical';

const props = withDefaults(
    defineProps<{
        set: ObservationSet | null;
        /** Codes to show, in order. Blood pressure is one tile. */
        codes?: string[];
        columns?: 3 | 6;
    }>(),
    {
        codes: () => [
            'temperature',
            'blood_pressure',
            'weight',
            'pulse',
            'respiratory_rate',
            'spo2',
        ],
        columns: 3,
    },
);

type Tile = { key: string; value: string; label: string; level: string };

const tiles = computed<Tile[]>(() => {
    const set = props.set;

    if (!set) {
        return [];
    }

    const byCode = new Map(set.readings.map((r) => [r.code, r]));
    const out: Tile[] = [];

    for (const code of props.codes) {
        if (code === 'blood_pressure') {
            if (set.blood_pressure) {
                const s = byCode.get('systolic_bp');
                const d = byCode.get('diastolic_bp');
                const level =
                    s?.level === 'critical' || d?.level === 'critical'
                        ? 'critical'
                        : s?.level === 'warning' || d?.level === 'warning'
                          ? 'warning'
                          : 'normal';
                out.push({
                    key: code,
                    value: set.blood_pressure,
                    label: 'BP mmHg',
                    level,
                });
            }

            continue;
        }

        const r = byCode.get(code);

        if (r) {
            out.push({
                key: code,
                value: r.display,
                label: r.short_label,
                level: r.level,
            });
        }
    }

    return out;
});
</script>

<template>
    <dl
        v-if="tiles.length"
        class="grid gap-x-3 gap-y-4"
        :class="columns === 6 ? 'grid-cols-3 sm:grid-cols-6' : 'grid-cols-3'"
    >
        <div v-for="tile in tiles" :key="tile.key" :title="tile.label">
            <dd
                class="text-base leading-tight font-semibold"
                :class="levelTextClass(tile.level)"
            >
                {{ tile.value }}
            </dd>
            <dt class="mt-0.5 text-[11px] text-muted-foreground">
                {{ tile.label }}
            </dt>
        </div>
    </dl>
    <p v-else class="text-sm text-muted-foreground">
        <slot name="empty">No observations recorded for this visit.</slot>
    </p>
</template>
