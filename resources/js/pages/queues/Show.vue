<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    ExternalLink,
    UserCog,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ObservationChips from '@/components/observations/ObservationChips.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ObservationSet,
    Option,
    Personnel,
    ServicePointOption,
} from '@/types/clinical';

/**
 * Queue management for one service point. Patients are attended to from
 * the module console; here a supervisor or records officer fixes who holds
 * a patient, where they are queued, or removes an entry.
 */
type Entry = {
    id: number;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    note: string | null;
    queued_at: string | null;
    started_at: string | null;
    assigned_to: string | null;
    assigned_to_id: number | null;
    assigned_to_me: boolean;
    routed_by: string | null;
    visit_number: string | null;
    latest_observations: ObservationSet | null;
    patient: {
        file_number: string;
        name: string;
        initials: string;
        sex: string;
        age: number | null;
        url: string;
    };
};

const props = defineProps<{
    servicePoint: {
        name: string;
        slug: string;
        description: string | null;
        console_url: string | null;
    };
    entries: Entry[];
    personnel: Personnel[];
    otherServicePoints: ServicePointOption[];
    priorities: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Service Queues', href: '/queues' }],
    },
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

// One action panel open at a time, per entry.
const openPanel = ref<{ id: number; kind: 'assign' | 'reroute' } | null>(null);

function isOpen(entry: Entry, kind: 'assign' | 'reroute'): boolean {
    return openPanel.value?.id === entry.id && openPanel.value.kind === kind;
}

const assignForm = useForm({ assigned_to: 'none' });

function openAssign(entry: Entry) {
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.assigned_to = entry.assigned_to_id
        ? String(entry.assigned_to_id)
        : 'none';
    openPanel.value = { id: entry.id, kind: 'assign' };
}

function submitAssign(entry: Entry) {
    assignForm
        .transform((d) => ({
            assigned_to:
                d.assigned_to === 'none' ? null : Number(d.assigned_to),
        }))
        .patch(`/queue-entries/${entry.id}/assign`, {
            preserveScroll: true,
            onSuccess: () => {
                openPanel.value = null;
            },
            onFinish: () => assignForm.transform((d) => d),
        });
}

const rerouteForm = useForm({
    service_point_id: '',
    priority: 'normal',
    note: '',
});

function openReroute(entry: Entry) {
    rerouteForm.reset();
    rerouteForm.clearErrors();
    rerouteForm.priority = entry.priority;
    openPanel.value = { id: entry.id, kind: 'reroute' };
}

function submitReroute(entry: Entry) {
    rerouteForm
        .transform((d) => ({
            service_point_id: Number(d.service_point_id),
            priority: d.priority,
            note: d.note || null,
        }))
        .post(`/queue-entries/${entry.id}/reroute`, {
            preserveScroll: true,
            onSuccess: () => {
                openPanel.value = null;
            },
            onFinish: () => rerouteForm.transform((d) => d),
        });
}

function cancel(entry: Entry) {
    router.post(
        `/queue-entries/${entry.id}/cancel`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`${servicePoint.name} — queue`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Link
            href="/queues"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            All queues
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ servicePoint.name }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ entries.length }}
                    {{ entries.length === 1 ? 'patient' : 'patients' }} in the
                    queue. Reassign, re-route or cancel entries here; attend to
                    patients from the console.
                </p>
            </div>
            <Button v-if="servicePoint.console_url" as-child>
                <Link :href="servicePoint.console_url">
                    <ExternalLink class="size-4" />
                    Open console
                </Link>
            </Button>
        </div>

        <div
            v-if="!entries.length"
            class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
        >
            The queue is empty.
        </div>

        <div class="flex flex-col gap-3">
            <div
                v-for="entry in entries"
                :key="entry.id"
                class="rounded-xl border border-border bg-card p-4"
                :class="
                    entry.status === 'in_service'
                        ? 'border-primary/40 ring-1 ring-primary/20'
                        : ''
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                        >
                            {{ entry.patient.initials }}
                        </span>
                        <div>
                            <Link
                                :href="entry.patient.url"
                                class="font-medium hover:underline"
                                >{{ entry.patient.name }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                <span class="font-mono">{{
                                    entry.patient.file_number
                                }}</span>
                                · {{ entry.patient.sex
                                }}{{
                                    entry.patient.age !== null
                                        ? ' · ' + entry.patient.age + 'y'
                                        : ''
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                            :class="priorityClass(entry.priority)"
                            >{{ entry.priority_label }}</span
                        >
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                entry.status === 'in_service'
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ entry.status_label }}</span
                        >
                        <span
                            v-if="entry.assigned_to"
                            class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                            >{{
                                entry.assigned_to_me ? 'You' : entry.assigned_to
                            }}</span
                        >
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                            >Unassigned</span
                        >
                    </div>
                </div>

                <div
                    class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <span v-if="entry.status === 'waiting'"
                        >Queued {{ entry.queued_at }}</span
                    >
                    <span v-else>Started {{ entry.started_at }}</span>
                    <span v-if="entry.routed_by"
                        >Routed by {{ entry.routed_by }}</span
                    >
                    <span v-if="entry.visit_number" class="font-mono">{{
                        entry.visit_number
                    }}</span>
                </div>

                <p
                    v-if="entry.note"
                    class="mt-2 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
                >
                    {{ entry.note }}
                </p>

                <div v-if="entry.latest_observations" class="mt-3">
                    <ObservationChips :set="entry.latest_observations" />
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openAssign(entry)"
                    >
                        <UserCog class="size-4" />
                        Reassign
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="openReroute(entry)"
                    >
                        <ArrowRightLeft class="size-4" />
                        Re-route
                    </Button>
                    <Button variant="ghost" size="sm" @click="cancel(entry)">
                        <X class="size-4" />
                        Cancel entry
                    </Button>
                </div>

                <form
                    v-if="isOpen(entry, 'assign')"
                    class="mt-3 grid gap-3 rounded-lg border border-border bg-muted/30 p-3 sm:grid-cols-[1fr_auto]"
                    @submit.prevent="submitAssign(entry)"
                >
                    <div class="grid gap-1.5">
                        <Label>Assign to</Label>
                        <Select v-model="assignForm.assigned_to">
                            <SelectTrigger class="w-full bg-background">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >Unassigned pool</SelectItem
                                >
                                <SelectItem
                                    v-for="p in props.personnel"
                                    :key="p.id"
                                    :value="String(p.id)"
                                    >{{ p.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="assignForm.errors.assigned_to" />
                    </div>
                    <div class="flex items-end gap-2">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="assignForm.processing"
                        >
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="openPanel = null"
                        >
                            Dismiss
                        </Button>
                    </div>
                </form>

                <form
                    v-if="isOpen(entry, 'reroute')"
                    class="mt-3 grid gap-3 rounded-lg border border-border bg-muted/30 p-3 sm:grid-cols-2"
                    @submit.prevent="submitReroute(entry)"
                >
                    <div class="grid gap-1.5">
                        <Label>Move to</Label>
                        <Select v-model="rerouteForm.service_point_id">
                            <SelectTrigger class="w-full bg-background">
                                <SelectValue
                                    placeholder="Select service point"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="sp in props.otherServicePoints"
                                    :key="sp.id"
                                    :value="String(sp.id)"
                                    >{{ sp.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="rerouteForm.errors.service_point_id"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Priority</Label>
                        <Select v-model="rerouteForm.priority">
                            <SelectTrigger class="w-full bg-background">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="p in props.priorities"
                                    :key="p.value"
                                    :value="p.value"
                                    >{{ p.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Reason (optional)</Label>
                        <Input
                            v-model="rerouteForm.note"
                            class="bg-background"
                            placeholder="e.g. Routed to the wrong clinic"
                        />
                    </div>
                    <div class="flex gap-2 sm:col-span-2">
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="
                                rerouteForm.processing ||
                                !rerouteForm.service_point_id
                            "
                        >
                            Re-route
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="openPanel = null"
                        >
                            Dismiss
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
