<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import ObservationTiles from '@/components/observations/ObservationTiles.vue';
import type {
    Allergy,
    LabResult,
    Medication,
    ObservationSet,
    PatientAlert,
    Problem,
} from '@/types/clinical';

const props = defineProps<{
    observations: ObservationSet | null;
    alerts: PatientAlert[];
    allergies: Allergy[];
    problems: Problem[];
    medications: Medication[];
    labResults: LabResult[];
    patientUrl: string;
    /** Stage keys the "View all" links jump to. */
    stages: { problems?: string; medications?: string; labs?: string };
}>();

const emit = defineEmits<{ go: [stage: string] }>();

// Alerts: manual alerts plus flags inferred from severe allergies and abnormal
// observations for this visit.
const safetyAlerts = computed<Array<{ label: string; level: string }>>(() => {
    const list: Array<{ label: string; level: string }> = [];

    for (const a of props.alerts) {
        list.push({
            label: a.message,
            level:
                a.severity === 'critical'
                    ? 'critical'
                    : a.severity === 'info'
                      ? 'info'
                      : 'warning',
        });
    }

    for (const a of props.allergies) {
        if (a.severity === 'severe') {
            list.push({
                label: `Severe allergy: ${a.substance}`,
                level: 'critical',
            });
        }
    }

    for (const f of props.observations?.flags ?? []) {
        list.push({
            label: f.label,
            level: f.level === 'critical' ? 'critical' : 'warning',
        });
    }

    // Systolic and diastolic can both flag "Hypertension"; say it once.
    return list.filter(
        (a, i) => list.findIndex((b) => b.label === a.label) === i,
    );
});

const alertPillClass = (level: string) => {
    if (level === 'critical') {
        return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400';
    }

    if (level === 'info') {
        return 'border-amber-400/30 bg-amber-400/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400';
};

const labFlagClass = (flag: string | null) => {
    if (flag === 'critical') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400 ring-1 ring-red-500/30';
    }

    if (flag === 'high' || flag === 'low') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/30';
    }

    return 'text-foreground';
};

const resultedLabs = computed(() =>
    props.labResults.filter((l) => l.status === 'resulted').slice(0, 5),
);

defineExpose({ safetyAlerts });
</script>

<template>
    <aside
        class="flex flex-col gap-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto lg:pr-1"
    >
        <section class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Observations</h2>
                <Link
                    :href="patientUrl"
                    class="text-xs font-medium text-primary hover:underline"
                    >View trends</Link
                >
            </div>
            <ObservationTiles :set="observations" />
            <p
                v-if="observations?.recorded_at_diff"
                class="mt-3 text-[11px] text-muted-foreground/70"
            >
                Recorded {{ observations.recorded_at_diff }}
            </p>
        </section>

        <section class="rounded-xl border border-border bg-card p-4">
            <h2 class="mb-3 text-sm font-semibold">
                Alerts
                <span
                    v-if="safetyAlerts.length"
                    class="font-normal text-muted-foreground"
                    >({{ safetyAlerts.length }})</span
                >
            </h2>
            <ul v-if="safetyAlerts.length" class="flex flex-col gap-2">
                <li
                    v-for="(a, i) in safetyAlerts"
                    :key="i"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                    :class="alertPillClass(a.level)"
                >
                    <AlertTriangle class="size-4 shrink-0" />
                    <span class="min-w-0 flex-1 truncate font-medium">{{
                        a.label
                    }}</span>
                    <ChevronRight class="size-4 shrink-0 opacity-60" />
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No active alerts.
            </p>
        </section>

        <section class="rounded-xl border border-border bg-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">
                    Problem list
                    <span
                        v-if="problems.length"
                        class="font-normal text-muted-foreground"
                        >({{ problems.length }})</span
                    >
                </h2>
                <button
                    v-if="stages.problems"
                    type="button"
                    class="text-xs font-medium text-primary hover:underline"
                    @click="emit('go', stages.problems)"
                >
                    View all
                </button>
            </div>
            <ul v-if="problems.length" class="flex flex-col gap-2 text-sm">
                <li
                    v-for="p in problems"
                    :key="p.id"
                    class="flex items-center justify-between gap-2"
                >
                    <span
                        :class="
                            p.status === 'resolved'
                                ? 'text-muted-foreground line-through'
                                : 'text-foreground'
                        "
                        >{{ p.name }}</span
                    >
                    <span
                        class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground capitalize"
                        >{{ p.role ?? p.status }}</span
                    >
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No problem list yet.
            </p>
        </section>

        <section class="rounded-xl border border-border bg-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">
                    Current medications
                    <span
                        v-if="medications.length"
                        class="font-normal text-muted-foreground"
                        >({{ medications.length }})</span
                    >
                </h2>
                <button
                    v-if="stages.medications"
                    type="button"
                    class="text-xs font-medium text-primary hover:underline"
                    @click="emit('go', stages.medications)"
                >
                    View all
                </button>
            </div>
            <ul v-if="medications.length" class="flex flex-col gap-1.5 text-sm">
                <li
                    v-for="m in medications"
                    :key="m.id"
                    class="text-foreground"
                >
                    {{ m.label }}
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No active medications recorded.
            </p>
        </section>

        <section class="rounded-xl border border-border bg-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Recent labs</h2>
                <button
                    v-if="stages.labs"
                    type="button"
                    class="text-xs font-medium text-primary hover:underline"
                    @click="emit('go', stages.labs)"
                >
                    View all
                </button>
            </div>
            <dl v-if="resultedLabs.length" class="flex flex-col gap-2 text-sm">
                <div
                    v-for="l in resultedLabs"
                    :key="l.id"
                    class="flex items-center justify-between gap-2"
                >
                    <dt
                        class="truncate text-muted-foreground"
                        :title="l.resulted_at ?? undefined"
                    >
                        {{ l.name }}
                    </dt>
                    <dd
                        class="shrink-0 rounded px-1.5 font-medium"
                        :class="labFlagClass(l.flag)"
                    >
                        {{ l.display_value }}
                    </dd>
                </div>
            </dl>
            <p v-else class="text-sm text-muted-foreground">
                No lab results available.
            </p>
        </section>
    </aside>
</template>
