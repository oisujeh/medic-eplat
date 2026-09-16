<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList, HeartPulse, Stethoscope } from '@lucide/vue';
import ObservationChips from '@/components/observations/ObservationChips.vue';
import { Button } from '@/components/ui/button';
import type { RecentEncounter, WorklistEntry } from '@/types/clinical';

/**
 * The console worklist shared by the clinical and nursing modules.
 */
defineProps<{
    queue: WorklistEntry[];
    recent: RecentEncounter[];
    seesAll: boolean;
    variant: 'clinical' | 'nursing';
    title: string;
    description: string;
    emptyText: string;
    recentTitle: string;
    recentEmpty: string;
}>();

function priorityClass(priority: string): string {
    if (priority === 'emergency') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400';
    }

    if (priority === 'urgent') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ description }}
                <span v-if="!seesAll"
                    >Showing patients assigned to you and the unassigned
                    pool.</span
                >
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            <div class="flex flex-col gap-3">
                <div
                    v-if="!queue.length"
                    class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
                >
                    {{ emptyText }}
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
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
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

                    <div v-if="item.latest_observations" class="mt-3">
                        <ObservationChips :set="item.latest_observations" />
                    </div>
                    <p v-else class="mt-2 text-xs text-muted-foreground">
                        No observations recorded for this visit.
                    </p>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span class="text-xs text-muted-foreground">
                            <span v-if="item.status === 'waiting'"
                                >Waiting {{ item.waiting_since }}</span
                            >
                            <span v-else>{{
                                variant === 'clinical'
                                    ? 'In consultation'
                                    : 'Being attended to'
                            }}</span>
                        </span>
                        <Button as-child size="sm">
                            <Link :href="item.open_url">
                                <Stethoscope
                                    v-if="variant === 'clinical'"
                                    class="size-4"
                                />
                                <HeartPulse v-else class="size-4" />
                                {{
                                    item.status === 'in_service'
                                        ? 'Continue'
                                        : variant === 'clinical'
                                          ? 'Consult'
                                          : 'Attend'
                                }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <aside>
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <ClipboardList class="size-4 text-muted-foreground" />
                        {{ recentTitle }}
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
                                :href="e.url"
                                class="text-sm font-medium hover:underline"
                                >{{ e.patient_name }}</Link
                            >
                            <span class="text-xs text-muted-foreground">
                                {{ e.summary || 'No summary recorded' }}
                            </span>
                            <span
                                v-if="e.signed_at"
                                class="text-xs text-muted-foreground/70"
                                >{{ e.signed_at }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ recentEmpty }}
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
