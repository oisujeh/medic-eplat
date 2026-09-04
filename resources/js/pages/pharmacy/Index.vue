<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pill } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    queue: Array<{
        id: number;
        priority: string;
        priority_label: string;
        service_point: string;
        waiting_since: string | null;
        pending_scripts: number;
        url: string;
        patient: {
            name: string;
            initials: string;
            file_number: string;
            sex: string;
            age: number | null;
        };
    }>;
    recent: Array<{
        id: number;
        patient_name: string;
        items_count: number;
        total: number;
        at: string | null;
    }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pharmacy', href: '/pharmacy' }] },
});

function priorityClass(priority: string): string {
    if (priority === 'emergency') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400';
    }

    if (priority === 'urgent') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-muted text-muted-foreground';
}

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="Pharmacy" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Pharmacy</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Patients waiting to be dispensed.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            <div class="flex flex-col gap-3">
                <div
                    v-if="!queue.length"
                    class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
                >
                    No patients waiting at pharmacy.
                </div>

                <div
                    v-for="item in queue"
                    :key="item.id"
                    class="rounded-xl border border-border bg-card p-4"
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
                                    · {{ item.pending_scripts }} script(s)
                                </p>
                            </div>
                        </div>
                        <span
                            v-if="item.priority !== 'normal'"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="priorityClass(item.priority)"
                            >{{ item.priority_label }}</span
                        >
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span class="text-xs text-muted-foreground"
                            >Waiting {{ item.waiting_since }}</span
                        >
                        <Button as-child size="sm">
                            <Link :href="item.url">
                                <Pill class="size-4" />
                                Dispense
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <aside>
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">
                        Your recent dispenses
                    </h2>
                    <ul
                        v-if="recent.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="d in recent"
                            :key="d.id"
                            class="flex flex-col gap-0.5 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span class="text-sm font-medium">{{
                                d.patient_name
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ d.items_count }} item(s) ·
                                {{ money(d.total) }} · {{ d.at }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No dispenses yet.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
