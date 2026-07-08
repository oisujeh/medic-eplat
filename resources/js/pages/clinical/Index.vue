<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, Stethoscope } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { type Vitals, alertBadge, chipClass, vitalsChips } from '@/lib/vitals';

type QueueItem = {
    id: number;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    service_point: string;
    assigned_to: string | null;
    assigned_to_me: boolean;
    waiting_since: string | null;
    latest_vitals: Vitals | null;
    consult_url: string;
    patient: {
        name: string;
        initials: string;
        file_number: string;
        sex: string;
        age: number | null;
    };
};

defineProps<{
    queue: QueueItem[];
    seesAll: boolean;
    recent: Array<{
        id: number;
        patient_name: string;
        file_number: string;
        diagnosis: string | null;
        completed_at: string | null;
        patient_url: string;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clinical', href: '/clinical' }],
    },
});

function priorityClass(priority: string): string {
    if (priority === 'emergency')
        return 'bg-red-500/10 text-red-700 dark:text-red-400';
    if (priority === 'urgent')
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Clinical" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Clinical</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Consultation queue.
                <span v-if="!seesAll"
                    >Showing patients assigned to you and the unassigned
                    pool.</span
                >
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            <!-- Consultation queue -->
            <div class="flex flex-col gap-3">
                <div
                    v-if="!queue.length"
                    class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
                >
                    No patients awaiting consultation. Patients routed to a
                    clinical service point will appear here.
                </div>

                <div
                    v-for="item in queue"
                    :key="item.id"
                    class="rounded-xl border border-border bg-card p-4"
                    :class="
                        item.status === 'in_service'
                            ? 'border-primary/40 ring-1 ring-primary/20'
                            : ''
                    "
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                            >
                                {{ item.patient.initials }}
                            </span>
                            <div>
                                <p class="font-medium">
                                    {{ item.patient.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-mono">{{
                                        item.patient.file_number
                                    }}</span>
                                    · {{ item.patient.sex
                                    }}{{
                                        item.patient.age !== null
                                            ? ' · ' + item.patient.age + 'y'
                                            : ''
                                    }}
                                    · {{ item.service_point }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="priorityClass(item.priority)"
                                >{{ item.priority_label }}</span
                            >
                            <span
                                v-if="item.assigned_to_me"
                                class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                >Assigned to you</span
                            >
                            <span
                                v-else-if="!item.assigned_to"
                                class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                                >Unassigned</span
                            >
                        </div>
                    </div>

                    <!-- Vitals from triage -->
                    <div v-if="item.latest_vitals" class="mt-3">
                        <div
                            v-if="alertBadge(item.latest_vitals.alert_level)"
                            class="mb-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="alertBadge(item.latest_vitals.alert_level)!.class"
                        >
                            {{ alertBadge(item.latest_vitals.alert_level)!.label }}:
                            {{
                                item.latest_vitals.flags
                                    .map((f) => f.label)
                                    .join(', ')
                            }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="chip in vitalsChips(item.latest_vitals)"
                                :key="chip.label"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs"
                                :class="chipClass(chip.level)"
                                :title="chip.reason ?? undefined"
                            >
                                <span
                                    :class="
                                        chip.level === 'normal'
                                            ? 'text-muted-foreground'
                                            : 'opacity-80'
                                    "
                                    >{{ chip.label }}</span
                                >
                                <span class="font-medium">{{ chip.value }}</span>
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-muted-foreground">
                        No vitals recorded for this visit.
                    </p>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span class="text-xs text-muted-foreground">
                            <span v-if="item.status === 'waiting'"
                                >Waiting {{ item.waiting_since }}</span
                            >
                            <span v-else>In consultation</span>
                        </span>
                        <Button as-child size="sm">
                            <Link :href="item.consult_url">
                                <Stethoscope class="size-4" />
                                {{
                                    item.status === 'in_service'
                                        ? 'Continue'
                                        : 'Consult'
                                }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Recently completed -->
            <aside>
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <ClipboardList class="size-4 text-muted-foreground" />
                        Your recent consultations
                    </h2>
                    <ul
                        v-if="recent.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="e in recent"
                            :key="e.id"
                            class="flex flex-col gap-0.5 py-2.5 first:pt-0 last:pb-0"
                        >
                            <Link
                                :href="e.patient_url"
                                class="text-sm font-medium hover:underline"
                                >{{ e.patient_name }}</Link
                            >
                            <span class="text-xs text-muted-foreground">
                                {{ e.diagnosis || 'No diagnosis recorded' }}
                            </span>
                            <span
                                v-if="e.completed_at"
                                class="text-xs text-muted-foreground/70"
                                >{{ e.completed_at }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No completed consultations yet.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
