<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    CircleDot,
    Send,
    Stethoscope,
    User,
    Waypoints,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import VitalsTrends from '@/components/VitalsTrends.vue';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { type Vitals, alertBadge, chipClass, vitalsChips } from '@/lib/vitals';

type Patient = {
    id: number;
    file_number: string;
    title: string | null;
    full_name: string;
    initials: string;
    surname: string;
    first_name: string;
    other_names: string | null;
    date_of_birth: string | null;
    date_of_birth_label: string | null;
    age: number | null;
    sex: string;
    sex_label: string;
    marital_status: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    nationality: string;
    state: string | null;
    lga: string | null;
    next_of_kin_name: string | null;
    next_of_kin_relationship: string | null;
    next_of_kin_phone: string | null;
    coverage: string;
    coverage_label: string;
    hmo_name: string | null;
    hmo_number: string | null;
    is_transfer: boolean;
    transfer_from: string | null;
    transfer_reason: string | null;
    transfer_service: string | null;
    visit_category: string;
    outpatient_service: string | null;
    registered_by: string | null;
    registered_at: string | null;
    registered_at_diff: string | null;
};

type QueueEntry = {
    id: number;
    service_point: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    note: string | null;
    assigned_to: string | null;
    routed_by: string | null;
    queued_at: string | null;
    started_at: string | null;
    completed_at: string | null;
};

type OpenVisit = {
    id: number;
    visit_number: string;
    reason: string | null;
    opened_at: string | null;
    opened_at_diff: string | null;
    entries: QueueEntry[];
};

type Encounter = {
    id: number;
    presenting_complaint: string | null;
    history: string | null;
    examination: string | null;
    diagnosis: string | null;
    plan: string | null;
    status: string;
    clinician: string | null;
    date: string | null;
};

const props = defineProps<{
    patient: Patient;
    openVisit: OpenVisit | null;
    vitals: Vitals[];
    encounters: Encounter[];
    servicePoints: Array<{
        id: number;
        name: string;
        personnel: Array<{ id: number; name: string }>;
    }>;
    routeOptions: {
        priorities: Array<{ value: string; label: string }>;
        visitReasons: string[];
    };
    canRoute: boolean;
}>();

const latestVitals = computed<Vitals | null>(() => props.vitals[0] ?? null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Patients', href: '/patients' },
            { title: 'Profile', href: '#' },
        ],
    },
});

const activeTab = ref('overview');

// The queue entry the patient is currently sitting at (waiting/in service),
// used for the at-a-glance status on the Overview tab.
const currentStage = computed<QueueEntry | null>(() => {
    if (!props.openVisit) return null;
    const entries = props.openVisit.entries;
    return (
        [...entries]
            .reverse()
            .find((e) => e.status === 'waiting' || e.status === 'in_service') ??
        entries[entries.length - 1] ??
        null
    );
});

const showRouteForm = ref(false);

// Jump to the Visit tab and open the routing form (used by shortcuts).
function startRouting() {
    activeTab.value = 'visit';
    showRouteForm.value = true;
}

const routeForm = useForm({
    service_point_id: '',
    assigned_to: 'none',
    priority: 'normal',
    visit_reason: props.openVisit ? '' : 'New visit',
    note: '',
});

// Personnel eligible for the chosen destination service point.
const routePersonnel = computed<Array<{ id: number; name: string }>>(
    () =>
        props.servicePoints.find(
            (sp) => String(sp.id) === routeForm.service_point_id,
        )?.personnel ?? [],
);

// Reset the assignee whenever the destination changes.
watch(
    () => routeForm.service_point_id,
    () => {
        routeForm.assigned_to = 'none';
    },
);

function submitRoute() {
    routeForm
        .transform((data) => ({
            ...data,
            service_point_id: Number(data.service_point_id),
            assigned_to:
                data.assigned_to === 'none' ? null : Number(data.assigned_to),
        }))
        .post(`/patients/${props.patient.id}/route`, {
            preserveScroll: true,
            onSuccess: () => {
                routeForm.reset();
                showRouteForm.value = false;
            },
        });
}

function closeVisit() {
    if (!props.openVisit) return;
    router.post(
        `/visits/${props.openVisit.id}/close`,
        {},
        { preserveScroll: true },
    );
}

function statusClass(status: string): string {
    if (status === 'in_service') return 'bg-primary/10 text-primary';
    if (status === 'completed')
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    if (status === 'cancelled') return 'bg-muted text-muted-foreground line-through';
    return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
}

type Field = { label: string; value: string | null };

const identity = computed<Field[]>(() => [
    { label: 'Title', value: props.patient.title },
    { label: 'Surname', value: props.patient.surname },
    { label: 'First name', value: props.patient.first_name },
    { label: 'Other names', value: props.patient.other_names },
    { label: 'Date of birth', value: props.patient.date_of_birth_label },
    {
        label: 'Age',
        value: props.patient.age !== null ? `${props.patient.age} years` : null,
    },
    { label: 'Sex', value: props.patient.sex_label },
    { label: 'Marital status', value: props.patient.marital_status },
    { label: 'Nationality', value: props.patient.nationality },
]);

const contact = computed<Field[]>(() => [
    { label: 'Phone', value: props.patient.phone },
    { label: 'Email', value: props.patient.email },
    { label: 'Residential address', value: props.patient.address },
    { label: 'State of residence', value: props.patient.state },
    { label: 'LGA of residence', value: props.patient.lga },
]);

const nextOfKin = computed<Field[]>(() => [
    { label: 'Name', value: props.patient.next_of_kin_name },
    { label: 'Relationship', value: props.patient.next_of_kin_relationship },
    { label: 'Phone', value: props.patient.next_of_kin_phone },
]);

const billing = computed<Field[]>(() => [
    { label: 'Coverage', value: props.patient.coverage_label },
    { label: 'HMO / provider', value: props.patient.hmo_name },
    { label: 'Enrollee number', value: props.patient.hmo_number },
]);

const visit = computed<Field[]>(() => [
    { label: 'Visit category', value: props.patient.visit_category },
    { label: 'Service point', value: props.patient.outpatient_service },
]);
</script>

<template>
    <Head :title="props.patient.full_name" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 p-4">
        <Link
            href="/patients"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to patients
        </Link>

        <!-- Profile header -->
        <div
            class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-border bg-card p-5"
        >
            <div class="flex items-center gap-4">
                <span
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary"
                >
                    {{ props.patient.initials }}
                </span>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ props.patient.full_name }}
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        <span class="font-mono">{{
                            props.patient.file_number
                        }}</span>
                        · {{ props.patient.sex_label
                        }}{{
                            props.patient.age !== null
                                ? ' · ' + props.patient.age + 'y'
                                : ''
                        }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                props.patient.coverage === 'hmo'
                                    ? 'bg-teal-500/10 text-teal-700 dark:text-teal-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ props.patient.coverage_label }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ props.patient.visit_category }}
                        </span>
                        <span
                            v-if="props.patient.is_transfer"
                            class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                        >
                            Transferred in
                        </span>
                        <span
                            v-if="currentStage"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                        >
                            At: {{ currentStage.service_point }} ·
                            {{ currentStage.status_label }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-end gap-2">
                <div v-if="props.canRoute" class="flex gap-2">
                    <Button size="sm" @click="startRouting">
                        <Send class="size-4" />
                        Send to service point
                    </Button>
                    <Button
                        v-if="props.openVisit"
                        variant="outline"
                        size="sm"
                        @click="closeVisit"
                    >
                        Close visit
                    </Button>
                </div>
                <div class="text-right text-xs text-muted-foreground">
                    <p v-if="props.patient.registered_at">
                        Registered {{ props.patient.registered_at }}
                    </p>
                    <p v-if="props.patient.registered_by">
                        by {{ props.patient.registered_by }}
                    </p>
                </div>
            </div>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="overview">Overview</TabsTrigger>
                <TabsTrigger value="demographics">
                    <User /> Demographics
                </TabsTrigger>
                <TabsTrigger value="visit">
                    <Waypoints /> Visit &amp; routing
                </TabsTrigger>
                <TabsTrigger value="vitals"> <Activity /> Vitals </TabsTrigger>
                <TabsTrigger value="clinical">
                    <Stethoscope /> Clinical
                </TabsTrigger>
            </TabsList>

            <!-- ===== OVERVIEW ===== -->
            <TabsContent value="overview" class="flex flex-col gap-6">
                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <!-- Current status -->
                        <section
                            class="rounded-xl border border-border bg-card p-5"
                        >
                            <h2 class="mb-3 text-sm font-semibold">
                                Current status
                            </h2>
                            <div v-if="props.openVisit">
                                <p class="text-sm text-muted-foreground">
                                    <span class="font-mono">{{
                                        props.openVisit.visit_number
                                    }}</span>
                                    · opened {{ props.openVisit.opened_at_diff }}
                                </p>
                                <div
                                    v-if="currentStage"
                                    class="mt-3 flex items-center gap-2"
                                >
                                    <span class="text-sm font-medium">{{
                                        currentStage.service_point
                                    }}</span>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="statusClass(currentStage.status)"
                                        >{{ currentStage.status_label }}</span
                                    >
                                </div>
                                <Button
                                    variant="link"
                                    class="mt-2 h-auto p-0 text-xs"
                                    @click="activeTab = 'visit'"
                                >
                                    View full timeline →
                                </Button>
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-start gap-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    No open visit.
                                </p>
                                <Button
                                    v-if="props.canRoute"
                                    size="sm"
                                    @click="startRouting"
                                >
                                    <Send class="size-4" />
                                    Send to service point
                                </Button>
                            </div>
                        </section>

                        <!-- Latest vitals -->
                        <section
                            class="rounded-xl border border-border bg-card p-5"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-sm font-semibold">
                                        Latest vitals
                                    </h2>
                                    <span
                                        v-if="
                                            latestVitals &&
                                            alertBadge(latestVitals.alert_level)
                                        "
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            alertBadge(latestVitals.alert_level)!
                                                .class
                                        "
                                        >{{
                                            alertBadge(latestVitals.alert_level)!
                                                .label
                                        }}</span
                                    >
                                </div>
                                <Button
                                    v-if="props.vitals.length"
                                    variant="link"
                                    class="h-auto p-0 text-xs"
                                    @click="activeTab = 'vitals'"
                                >
                                    View trends →
                                </Button>
                            </div>
                            <div
                                v-if="latestVitals"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="chip in vitalsChips(latestVitals)"
                                    :key="chip.label"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm"
                                    :class="chipClass(chip.level)"
                                    :title="chip.reason ?? undefined"
                                >
                                    <span
                                        class="text-xs"
                                        :class="
                                            chip.level === 'normal'
                                                ? 'text-muted-foreground'
                                                : 'opacity-80'
                                        "
                                        >{{ chip.label }}</span
                                    >
                                    <span class="font-semibold">{{
                                        chip.value
                                    }}</span>
                                </span>
                            </div>
                            <p v-else class="mt-2 text-sm text-muted-foreground">
                                No vitals recorded yet.
                            </p>
                            <p
                                v-if="latestVitals && latestVitals.flags.length"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Flags:
                                <span class="font-medium text-foreground">{{
                                    latestVitals.flags
                                        .map((f) => f.label)
                                        .join(', ')
                                }}</span>
                            </p>
                        </section>
                    </div>

                    <!-- At a glance -->
                    <aside>
                        <section
                            class="rounded-xl border border-border bg-card p-5"
                        >
                            <h2 class="mb-3 text-sm font-semibold">
                                At a glance
                            </h2>
                            <dl class="flex flex-col gap-3">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Age / Sex
                                    </dt>
                                    <dd class="text-sm">
                                        {{
                                            props.patient.age !== null
                                                ? props.patient.age + ' yrs'
                                                : '—'
                                        }}
                                        · {{ props.patient.sex_label }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd class="text-sm">
                                        {{ props.patient.phone || '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Coverage
                                    </dt>
                                    <dd class="text-sm">
                                        {{ props.patient.coverage_label }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Residence
                                    </dt>
                                    <dd class="text-sm">
                                        {{
                                            props.patient.lga
                                                ? props.patient.lga +
                                                  ', ' +
                                                  props.patient.state
                                                : props.patient.state || '—'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Next of kin
                                    </dt>
                                    <dd class="text-sm">
                                        {{
                                            props.patient.next_of_kin_name || '—'
                                        }}<span
                                            v-if="
                                                props.patient
                                                    .next_of_kin_relationship
                                            "
                                            class="text-muted-foreground"
                                        >
                                            ({{
                                                props.patient
                                                    .next_of_kin_relationship
                                            }})</span
                                        >
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </aside>
                </div>
            </TabsContent>

            <!-- ===== VISIT & ROUTING ===== -->
            <TabsContent value="visit">
                <section class="rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 class="text-sm font-semibold">
                            Current visit &amp; routing
                        </h2>
                        <p
                            v-if="props.openVisit"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            <span class="font-mono">{{
                                props.openVisit.visit_number
                            }}</span>
                            · opened {{ props.openVisit.opened_at_diff }}
                            <span v-if="props.openVisit.reason">
                                · {{ props.openVisit.reason }}</span
                            >
                        </p>
                        <p v-else class="mt-0.5 text-xs text-muted-foreground">
                            No open visit. Use “Send to service point” above to
                            begin.
                        </p>
                    </div>

                    <!-- Routing form -->
            <div
                v-if="showRouteForm && props.canRoute"
                class="mt-4 grid gap-3 rounded-lg border border-border bg-muted/30 p-4 sm:grid-cols-2"
            >
                <div class="grid gap-1.5">
                    <Label>Service point *</Label>
                    <Select v-model="routeForm.service_point_id">
                        <SelectTrigger class="w-full bg-background">
                            <SelectValue placeholder="Select destination" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sp in props.servicePoints"
                                :key="sp.id"
                                :value="String(sp.id)"
                                >{{ sp.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="routeForm.errors.service_point_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label>Priority</Label>
                    <Select v-model="routeForm.priority">
                        <SelectTrigger class="w-full bg-background">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in props.routeOptions.priorities"
                                :key="p.value"
                                :value="p.value"
                                >{{ p.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>

                <div
                    v-if="routeForm.service_point_id"
                    class="grid gap-1.5 sm:col-span-2"
                >
                    <Label>Assign to personnel</Label>
                    <Select v-model="routeForm.assigned_to">
                        <SelectTrigger class="w-full bg-background">
                            <SelectValue
                                placeholder="Unassigned — anyone at this point"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none"
                                >Unassigned — anyone at this point</SelectItem
                            >
                            <SelectItem
                                v-for="person in routePersonnel"
                                :key="person.id"
                                :value="String(person.id)"
                                >{{ person.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p
                        v-if="!routePersonnel.length"
                        class="text-xs text-muted-foreground"
                    >
                        No named staff configured for this point — it will go to
                        the shared pool.
                    </p>
                </div>

                <div v-if="!props.openVisit" class="grid gap-1.5">
                    <Label>Visit reason</Label>
                    <Select v-model="routeForm.visit_reason">
                        <SelectTrigger class="w-full bg-background">
                            <SelectValue placeholder="Select" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="r in props.routeOptions.visitReasons"
                                :key="r"
                                :value="r"
                                >{{ r }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>

                <div
                    class="grid gap-1.5"
                    :class="props.openVisit ? '' : 'sm:col-span-2'"
                >
                    <Label>Note (optional)</Label>
                    <Input
                        v-model="routeForm.note"
                        placeholder="e.g. Take vitals before consultation"
                        class="bg-background"
                    />
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <Button
                        size="sm"
                        :disabled="routeForm.processing"
                        @click="submitRoute"
                    >
                        Send to queue
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="showRouteForm = false"
                    >
                        Cancel
                    </Button>
                </div>
            </div>

            <!-- Visit timeline -->
            <ol
                v-if="props.openVisit && props.openVisit.entries.length"
                class="mt-4 flex flex-col gap-3"
            >
                <li
                    v-for="entry in props.openVisit.entries"
                    :key="entry.id"
                    class="flex gap-3"
                >
                    <div class="flex flex-col items-center">
                        <CircleDot class="size-4 text-muted-foreground" />
                        <span class="mt-1 w-px flex-1 bg-border" />
                    </div>
                    <div class="flex-1 pb-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium">{{
                                entry.service_point
                            }}</span>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusClass(entry.status)"
                            >
                                {{ entry.status_label }}
                            </span>
                            <span
                                v-if="entry.priority !== 'normal'"
                                class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 capitalize dark:text-amber-400"
                            >
                                {{ entry.priority_label }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <span v-if="entry.queued_at"
                                >Queued {{ entry.queued_at }}</span
                            >
                            <span v-if="entry.started_at">
                                · started {{ entry.started_at }}</span
                            >
                            <span v-if="entry.completed_at">
                                · completed {{ entry.completed_at }}</span
                            >
                            <span v-if="entry.assigned_to">
                                · {{ entry.assigned_to }}</span
                            >
                        </p>
                        <p
                            v-if="entry.note"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ entry.note }}
                        </p>
                    </div>
                </li>
            </ol>
                </section>
            </TabsContent>

            <!-- ===== VITALS ===== -->
            <TabsContent value="vitals">
                <section class="rounded-xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold">
                        Vitals &amp; anthropometrics
                    </h2>
                    <span
                        v-if="latestVitals && alertBadge(latestVitals.alert_level)"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="alertBadge(latestVitals.alert_level)!.class"
                    >
                        {{ alertBadge(latestVitals.alert_level)!.label }}
                    </span>
                </div>
                <span
                    v-if="latestVitals"
                    class="text-xs text-muted-foreground"
                >
                    Last recorded {{ latestVitals.recorded_at_diff }}
                    <span v-if="latestVitals.recorded_by"
                        >· {{ latestVitals.recorded_by }}</span
                    >
                </span>
            </div>

            <div
                v-if="latestVitals && latestVitals.flags.length"
                class="mt-2 text-xs text-muted-foreground"
            >
                Flags:
                <span class="font-medium text-foreground">{{
                    latestVitals.flags.map((f) => f.label).join(', ')
                }}</span>
            </div>

            <div v-if="latestVitals" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="chip in vitalsChips(latestVitals)"
                    :key="chip.label"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm"
                    :class="chipClass(chip.level)"
                    :title="chip.reason ?? undefined"
                >
                    <span
                        class="text-xs"
                        :class="
                            chip.level === 'normal'
                                ? 'text-muted-foreground'
                                : 'opacity-80'
                        "
                        >{{ chip.label }}</span
                    >
                    <span class="font-semibold">{{ chip.value }}</span>
                </span>
            </div>

            <VitalsTrends v-if="props.vitals.length > 1" :vitals="props.vitals" />
            <p v-else class="mt-2 text-sm text-muted-foreground">
                No vitals recorded yet. These are captured at Triage during a visit.
            </p>

            <p
                v-if="latestVitals?.notes"
                class="mt-2 text-xs text-muted-foreground"
            >
                {{ latestVitals.notes }}
            </p>

            <div
                v-if="props.vitals.length > 1"
                class="mt-4 border-t border-border pt-3"
            >
                <p class="mb-2 text-xs font-semibold text-muted-foreground">
                    Earlier readings
                </p>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="v in props.vitals.slice(1)"
                        :key="v.id"
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                    >
                        <span class="text-muted-foreground">{{
                            v.recorded_at
                        }}</span>
                        <span
                            v-for="chip in vitalsChips(v)"
                            :key="chip.label"
                            class="text-muted-foreground"
                        >
                            {{ chip.label }}
                            <span class="font-medium text-foreground">{{
                                chip.value
                            }}</span>
                        </span>
                    </li>
                </ul>
            </div>
                </section>
            </TabsContent>

            <!-- ===== DEMOGRAPHICS ===== -->
            <TabsContent value="demographics">
                <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold">Identity</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <div v-for="f in identity" :key="f.label" class="min-w-0">
                        <dt class="text-xs text-muted-foreground">
                            {{ f.label }}
                        </dt>
                        <dd class="truncate text-sm">{{ f.value || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold">Contact &amp; residence</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <div v-for="f in contact" :key="f.label" class="min-w-0">
                        <dt class="text-xs text-muted-foreground">
                            {{ f.label }}
                        </dt>
                        <dd class="text-sm">{{ f.value || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold">Next of kin</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <div v-for="f in nextOfKin" :key="f.label" class="min-w-0">
                        <dt class="text-xs text-muted-foreground">
                            {{ f.label }}
                        </dt>
                        <dd class="text-sm">{{ f.value || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold">Billing coverage</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                    <div v-for="f in billing" :key="f.label" class="min-w-0">
                        <dt class="text-xs text-muted-foreground">
                            {{ f.label }}
                        </dt>
                        <dd class="text-sm">{{ f.value || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-xl border border-border bg-card p-5 md:col-span-2"
            >
                <h2 class="mb-4 text-sm font-semibold">Visit &amp; routing</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                    <div v-for="f in visit" :key="f.label" class="min-w-0">
                        <dt class="text-xs text-muted-foreground">
                            {{ f.label }}
                        </dt>
                        <dd class="text-sm">{{ f.value || '—' }}</dd>
                    </div>
                </dl>

                <div
                    v-if="props.patient.is_transfer"
                    class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4"
                >
                    <h3
                        class="mb-2 text-xs font-semibold text-amber-700 dark:text-amber-400"
                    >
                        Inter-facility transfer
                    </h3>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Transferred from
                            </dt>
                            <dd class="text-sm">
                                {{ props.patient.transfer_from || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Reason</dt>
                            <dd class="text-sm">
                                {{ props.patient.transfer_reason || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Service to provide
                            </dt>
                            <dd class="text-sm">
                                {{ props.patient.transfer_service || '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
                </div>
            </TabsContent>

            <!-- ===== CLINICAL ===== -->
            <TabsContent value="clinical">
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold">
                        Clinical encounters
                    </h2>
                    <ol
                        v-if="props.encounters.length"
                        class="flex flex-col gap-4"
                    >
                        <li
                            v-for="e in props.encounters"
                            :key="e.id"
                            class="rounded-lg border border-border p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span class="text-sm font-semibold">{{
                                    e.diagnosis || 'No diagnosis recorded'
                                }}</span>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        e.status === 'completed'
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                    "
                                    >{{
                                        e.status === 'completed'
                                            ? 'Completed'
                                            : 'Draft'
                                    }}</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ e.date }}
                                <span v-if="e.clinician">
                                    · {{ e.clinician }}</span
                                >
                            </p>
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div v-if="e.presenting_complaint">
                                    <dt class="text-xs text-muted-foreground">
                                        Presenting complaint
                                    </dt>
                                    <dd class="text-sm whitespace-pre-line">
                                        {{ e.presenting_complaint }}
                                    </dd>
                                </div>
                                <div v-if="e.examination">
                                    <dt class="text-xs text-muted-foreground">
                                        Examination
                                    </dt>
                                    <dd class="text-sm whitespace-pre-line">
                                        {{ e.examination }}
                                    </dd>
                                </div>
                                <div v-if="e.plan" class="sm:col-span-2">
                                    <dt class="text-xs text-muted-foreground">
                                        Plan
                                    </dt>
                                    <dd class="text-sm whitespace-pre-line">
                                        {{ e.plan }}
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        No clinical encounters yet. These are documented by a
                        clinician during a consultation.
                    </p>
                </section>
            </TabsContent>
        </Tabs>
    </div>
</template>
