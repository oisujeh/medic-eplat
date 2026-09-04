<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    MoreHorizontal,
    Search,
    UserPlus,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Appointment = {
    id: number;
    status: string;
    status_label: string;
    source: string;
    source_label: string;
    priority: string;
    reason: string | null;
    note: string | null;
    start: string;
    end: string;
    duration: number;
    time_label: string;
    date: string;
    can_check_in: boolean;
    patient: { id: number; name: string; file_number: string; url: string };
    provider: string | null;
    provider_id: number | null;
    service_point: string;
    service_point_id: number;
};

type Option = { id: number; name: string };

const props = defineProps<{
    appointments: Appointment[];
    filters: {
        view: 'day' | 'week' | 'agenda';
        date: string;
        provider_id: number | null;
        service_point_id: number | null;
    };
    providers: Option[];
    servicePoints: Array<{ id: number; name: string; slug: string }>;
    priorities: Array<{ value: string; label: string }>;
    prefill: { id: number; name: string; file_number: string } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Appointments', href: '/appointments' }],
    },
});

// --- Navigation / filters ---
function go(params: Record<string, string | number | null>) {
    router.get(
        '/appointments',
        {
            view: props.filters.view,
            date: props.filters.date,
            provider_id: props.filters.provider_id,
            service_point_id: props.filters.service_point_id,
            ...params,
        },
        { preserveScroll: true, preserveState: false },
    );
}

function setView(view: string) {
    go({ view });
}

function shiftDate(direction: number) {
    const d = new Date(props.filters.date + 'T00:00:00');
    d.setDate(
        d.getDate() + direction * (props.filters.view === 'week' ? 7 : 1),
    );
    go({ date: toISODate(d) });
}

function toISODate(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const headingLabel = computed(() => {
    const d = new Date(props.filters.date + 'T00:00:00');

    if (props.filters.view === 'week') {
        const start = startOfWeek(d);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        return `${start.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })} – ${end.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}`;
    }

    if (props.filters.view === 'agenda') {
        return 'Upcoming';
    }

    return d.toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

function startOfWeek(d: Date): Date {
    const copy = new Date(d);
    copy.setDate(copy.getDate() - copy.getDay()); // Sunday start
    copy.setHours(0, 0, 0, 0);

    return copy;
}

// --- Status styling ---
const statusClass = (status: string) => {
    switch (status) {
        case 'checked_in':
            return 'border-green-500/30 bg-green-500/10 text-green-700 dark:text-green-400';
        case 'completed':
            return 'border-border bg-muted text-muted-foreground';
        case 'cancelled':
            return 'border-red-500/30 bg-red-500/5 text-red-700 line-through dark:text-red-400';
        case 'no_show':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400';
        default:
            return 'border-primary/30 bg-primary/10 text-foreground';
    }
};

// --- Day grid geometry ---
const DAY_START = 7;
const DAY_END = 19;
const HOUR_PX = 60;
const hours = Array.from(
    { length: DAY_END - DAY_START },
    (_, i) => DAY_START + i,
);

const dayAppointments = computed(() =>
    props.appointments.filter((a) => a.date === props.filters.date),
);

function blockStyle(a: Appointment) {
    const start = new Date(a.start);
    const minutes = (start.getHours() - DAY_START) * 60 + start.getMinutes();
    const top = Math.max(0, (minutes / 60) * HOUR_PX);
    const height = Math.max(22, (a.duration / 60) * HOUR_PX - 4);

    return { top: `${top}px`, height: `${height}px` };
}

// --- Week grouping ---
const weekDays = computed(() => {
    const start = startOfWeek(new Date(props.filters.date + 'T00:00:00'));

    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const iso = toISODate(d);

        return {
            iso,
            label: d.toLocaleDateString(undefined, { weekday: 'short' }),
            dayNum: d.getDate(),
            isToday: iso === toISODate(new Date()),
            items: props.appointments.filter((a) => a.date === iso),
        };
    });
});

// --- Agenda grouping ---
const agendaGroups = computed(() => {
    const groups: Record<string, Appointment[]> = {};

    for (const a of props.appointments) {
        (groups[a.date] ??= []).push(a);
    }

    return Object.entries(groups).map(([date, items]) => ({
        date,
        label: new Date(date + 'T00:00:00').toLocaleDateString(undefined, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        }),
        items,
    }));
});

// --- Appointment actions ---
function checkIn(a: Appointment) {
    router.post(`/appointments/${a.id}/check-in`, {}, { preserveScroll: true });
}
function cancel(a: Appointment) {
    router.post(`/appointments/${a.id}/cancel`, {}, { preserveScroll: true });
}
function noShow(a: Appointment) {
    router.post(`/appointments/${a.id}/no-show`, {}, { preserveScroll: true });
}

// --- Patient typeahead (shared by book + walk-in) ---
type PatientHit = { id: number; name: string; file_number: string };
const patientQuery = ref('');
const patientResults = ref<PatientHit[]>([]);
const selectedPatient = ref<PatientHit | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(patientQuery, (q) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (selectedPatient.value && q === selectedPatient.value.name) {
        return;
    }

    selectedPatient.value = null;

    if (q.trim().length < 2) {
        patientResults.value = [];

        return;
    }

    searchTimer = setTimeout(async () => {
        const res = await fetch(
            `/appointments/patient-search?q=${encodeURIComponent(q)}`,
            { headers: { Accept: 'application/json' } },
        );
        patientResults.value = (await res.json()).patients ?? [];
    }, 250);
});

function pickPatient(p: PatientHit) {
    selectedPatient.value = p;
    patientQuery.value = p.name;
    patientResults.value = [];
    bookForm.patient_id = p.id;
    walkInForm.patient_id = p.id;
}

// --- Slot loading (book + reschedule) ---
type Slot = { start: string; label: string; available: boolean };
const slots = ref<Slot[]>([]);
const slotsLoading = ref(false);

async function loadSlots(
    providerId: string,
    servicePointId: string,
    date: string,
) {
    if (!providerId || !date) {
        slots.value = [];

        return;
    }

    slotsLoading.value = true;
    const params = new URLSearchParams({ provider_id: providerId, date });

    if (servicePointId) {
        params.set('service_point_id', servicePointId);
    }

    const res = await fetch(`/appointments/slots?${params}`, {
        headers: { Accept: 'application/json' },
    });
    slots.value = (await res.json()).slots ?? [];
    slotsLoading.value = false;
}

// --- Book dialog ---
const bookOpen = ref(false);
const bookForm = useForm({
    patient_id: null as number | null,
    service_point_id: '',
    provider_id: '',
    scheduled_start: '',
    duration_minutes: 30,
    priority: 'normal',
    reason: '',
});
const bookDate = ref(props.filters.date);

function openBook() {
    bookForm.reset();
    bookForm.clearErrors();
    selectedPatient.value = null;
    patientQuery.value = '';
    patientResults.value = [];
    slots.value = [];
    bookDate.value = props.filters.date;
    bookOpen.value = true;
}

// Arriving from a patient profile pre-fills and opens the booking dialog.
onMounted(() => {
    if (props.prefill) {
        openBook();
        selectedPatient.value = props.prefill;
        bookForm.patient_id = props.prefill.id;
        patientQuery.value = props.prefill.name;
    }
});

watch(
    () => [bookForm.provider_id, bookForm.service_point_id, bookDate.value],
    () => {
        bookForm.scheduled_start = '';

        if (bookOpen.value) {
            loadSlots(
                bookForm.provider_id,
                bookForm.service_point_id,
                bookDate.value,
            );
        }
    },
);

function submitBook() {
    bookForm
        .transform((d) => ({
            ...d,
            provider_id: d.provider_id || null,
            service_point_id: Number(d.service_point_id),
        }))
        .post('/appointments', {
            preserveScroll: true,
            onSuccess: () => {
                bookOpen.value = false;
            },
        });
}

// --- Walk-in dialog ---
const walkInOpen = ref(false);
const walkInForm = useForm({
    patient_id: null as number | null,
    service_point_id: '',
    provider_id: '',
    priority: 'normal',
    reason: '',
});

function openWalkIn() {
    walkInForm.reset();
    walkInForm.clearErrors();
    selectedPatient.value = null;
    patientQuery.value = '';
    patientResults.value = [];
    walkInOpen.value = true;
}

function submitWalkIn() {
    walkInForm
        .transform((d) => ({
            ...d,
            provider_id: d.provider_id || null,
            service_point_id: Number(d.service_point_id),
        }))
        .post('/appointments/walk-in', {
            preserveScroll: true,
            onSuccess: () => {
                walkInOpen.value = false;
            },
        });
}

// --- Reschedule dialog ---
const rescheduleTarget = ref<Appointment | null>(null);
const rescheduleForm = useForm({ scheduled_start: '' });
const rescheduleDate = ref(props.filters.date);

function openReschedule(a: Appointment) {
    rescheduleTarget.value = a;
    rescheduleForm.reset();
    rescheduleForm.clearErrors();
    rescheduleDate.value = a.date;
    slots.value = [];

    if (a.provider_id) {
        loadSlots(String(a.provider_id), String(a.service_point_id), a.date);
    }
}

watch(rescheduleDate, (date) => {
    if (rescheduleTarget.value?.provider_id) {
        rescheduleForm.scheduled_start = '';
        loadSlots(
            String(rescheduleTarget.value.provider_id),
            String(rescheduleTarget.value.service_point_id),
            date,
        );
    }
});

function submitReschedule() {
    if (!rescheduleTarget.value) {
        return;
    }

    rescheduleForm.patch(`/appointments/${rescheduleTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            rescheduleTarget.value = null;
        },
    });
}

const views = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'agenda', label: 'Agenda' },
];
const durations = [15, 20, 30, 45, 60];
</script>

<template>
    <Head title="Appointments" />

    <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Appointments
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Schedule visits against provider availability, register
                    walk-ins and check patients into the queue.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline" size="sm">
                    <Link href="/appointments/schedules">
                        <Clock class="size-4" />
                        Provider schedules
                    </Link>
                </Button>
                <Button variant="outline" size="sm" @click="openWalkIn">
                    <UserPlus class="size-4" />
                    Walk-in
                </Button>
                <Button size="sm" @click="openBook">
                    <CalendarPlus class="size-4" />
                    Book
                </Button>
            </div>
        </div>

        <!-- Toolbar -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-3"
        >
            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-md border border-border p-0.5">
                    <button
                        v-for="v in views"
                        :key="v.value"
                        type="button"
                        class="rounded px-3 py-1 text-sm font-medium transition-colors"
                        :class="
                            filters.view === v.value
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="setView(v.value)"
                    >
                        {{ v.label }}
                    </button>
                </div>
                <div
                    v-if="filters.view !== 'agenda'"
                    class="flex items-center gap-1"
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Previous"
                        @click="shiftDate(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="go({ date: toISODate(new Date()) })"
                    >
                        Today
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Next"
                        @click="shiftDate(1)"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
                <span class="text-sm font-medium">{{ headingLabel }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Select
                    :model-value="
                        filters.provider_id
                            ? String(filters.provider_id)
                            : 'all'
                    "
                    @update:model-value="
                        (v) =>
                            go({ provider_id: v === 'all' ? null : Number(v) })
                    "
                >
                    <SelectTrigger class="h-8 w-44">
                        <SelectValue placeholder="All providers" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All providers</SelectItem>
                        <SelectItem
                            v-for="p in providers"
                            :key="p.id"
                            :value="String(p.id)"
                            >{{ p.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select
                    :model-value="
                        filters.service_point_id
                            ? String(filters.service_point_id)
                            : 'all'
                    "
                    @update:model-value="
                        (v) =>
                            go({
                                service_point_id:
                                    v === 'all' ? null : Number(v),
                            })
                    "
                >
                    <SelectTrigger class="h-8 w-44">
                        <SelectValue placeholder="All clinics" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All clinics</SelectItem>
                        <SelectItem
                            v-for="s in servicePoints"
                            :key="s.id"
                            :value="String(s.id)"
                            >{{ s.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- ===== Day view ===== -->
        <div
            v-if="filters.view === 'day'"
            class="rounded-xl border border-border bg-card p-4"
        >
            <div
                v-if="dayAppointments.length === 0"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                No appointments for this day.
            </div>
            <div
                class="relative"
                :style="{ height: `${hours.length * HOUR_PX}px` }"
            >
                <div
                    v-for="h in hours"
                    :key="h"
                    class="absolute right-0 left-14 border-t border-border/60"
                    :style="{ top: `${(h - DAY_START) * HOUR_PX}px` }"
                >
                    <span
                        class="absolute -top-2 -left-14 w-12 text-right text-[11px] text-muted-foreground"
                        >{{ String(h).padStart(2, '0') }}:00</span
                    >
                </div>
                <div class="absolute inset-y-0 right-0 left-14">
                    <button
                        v-for="a in dayAppointments"
                        :key="a.id"
                        type="button"
                        class="absolute right-1 left-1 overflow-hidden rounded-md border px-2 py-1 text-left text-xs"
                        :class="statusClass(a.status)"
                        :style="blockStyle(a)"
                        @click="openReschedule(a)"
                    >
                        <span class="font-medium"
                            >{{ a.time_label }} · {{ a.patient.name }}</span
                        >
                        <span class="block truncate opacity-80"
                            >{{ a.service_point
                            }}<template v-if="a.provider">
                                · {{ a.provider }}</template
                            ></span
                        >
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== Week view ===== -->
        <div
            v-else-if="filters.view === 'week'"
            class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7"
        >
            <div
                v-for="day in weekDays"
                :key="day.iso"
                class="flex flex-col rounded-xl border border-border bg-card"
            >
                <div
                    class="flex items-center justify-between border-b border-border px-2 py-1.5"
                    :class="day.isToday ? 'bg-primary/5' : ''"
                >
                    <span class="text-xs font-semibold">{{ day.label }}</span>
                    <span
                        class="flex size-5 items-center justify-center rounded-full text-xs"
                        :class="
                            day.isToday
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ day.dayNum }}</span
                    >
                </div>
                <div class="flex flex-1 flex-col gap-1 p-1.5">
                    <button
                        v-for="a in day.items"
                        :key="a.id"
                        type="button"
                        class="rounded border px-1.5 py-1 text-left text-[11px]"
                        :class="statusClass(a.status)"
                        @click="openReschedule(a)"
                    >
                        <span class="font-medium">{{ a.time_label }}</span>
                        <span class="block truncate">{{ a.patient.name }}</span>
                    </button>
                    <p
                        v-if="day.items.length === 0"
                        class="px-1 py-2 text-center text-[11px] text-muted-foreground/60"
                    >
                        —
                    </p>
                </div>
            </div>
        </div>

        <!-- ===== Agenda view ===== -->
        <div v-else class="flex flex-col gap-4">
            <div
                v-if="agendaGroups.length === 0"
                class="rounded-xl border border-border bg-card py-10 text-center text-sm text-muted-foreground"
            >
                No upcoming appointments.
            </div>
            <section
                v-for="group in agendaGroups"
                :key="group.date"
                class="rounded-xl border border-border bg-card"
            >
                <h2
                    class="border-b border-border px-4 py-2 text-sm font-semibold"
                >
                    {{ group.label }}
                </h2>
                <ul class="divide-y divide-border">
                    <li
                        v-for="a in group.items"
                        :key="a.id"
                        class="flex flex-wrap items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="inline-flex w-20 items-center gap-1 text-sm font-medium"
                        >
                            <Clock class="size-3.5 text-muted-foreground" />
                            {{ a.time_label }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="a.patient.url"
                                class="text-sm font-medium hover:underline"
                                >{{ a.patient.name }}</Link
                            >
                            <p class="truncate text-xs text-muted-foreground">
                                {{ a.service_point
                                }}<template v-if="a.provider">
                                    · {{ a.provider }}</template
                                ><template v-if="a.reason">
                                    · {{ a.reason }}</template
                                >
                            </p>
                        </div>
                        <span
                            class="rounded-md border px-2 py-0.5 text-[11px] font-medium"
                            :class="statusClass(a.status)"
                            >{{ a.status_label }}</span
                        >
                        <span
                            v-if="a.source !== 'booked'"
                            class="rounded bg-muted px-1.5 text-[11px] text-muted-foreground"
                            >{{ a.source_label }}</span
                        >
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Actions"
                                >
                                    <MoreHorizontal class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-if="a.can_check_in"
                                    @select="checkIn(a)"
                                >
                                    <Check class="size-4" />
                                    Check in
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="a.can_check_in"
                                    @select="openReschedule(a)"
                                >
                                    <CalendarDays class="size-4" />
                                    Reschedule
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="a.can_check_in"
                                    @select="noShow(a)"
                                >
                                    No-show
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="a.can_check_in"
                                    class="text-red-600 dark:text-red-400"
                                    @select="cancel(a)"
                                >
                                    Cancel
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </li>
                </ul>
            </section>
        </div>

        <!-- ===== Book dialog ===== -->
        <Dialog v-model:open="bookOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Book appointment</DialogTitle>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitBook">
                    <div class="grid gap-1.5">
                        <Label>Patient *</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="patientQuery"
                                placeholder="Search by name or file number…"
                                class="pl-8"
                            />
                            <ul
                                v-if="patientResults.length"
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-border bg-popover shadow-md"
                            >
                                <li
                                    v-for="p in patientResults"
                                    :key="p.id"
                                    class="cursor-pointer px-3 py-2 text-sm hover:bg-muted"
                                    @click="pickPatient(p)"
                                >
                                    <span class="font-medium">{{
                                        p.name
                                    }}</span>
                                    <span
                                        class="ml-1 text-xs text-muted-foreground"
                                        >{{ p.file_number }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <InputError :message="bookForm.errors.patient_id" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Clinic *</Label>
                            <Select v-model="bookForm.service_point_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select clinic" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="s in servicePoints"
                                        :key="s.id"
                                        :value="String(s.id)"
                                        >{{ s.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="bookForm.errors.service_point_id"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Provider</Label>
                            <Select v-model="bookForm.provider_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Any provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in providers"
                                        :key="p.id"
                                        :value="String(p.id)"
                                        >{{ p.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Date *</Label>
                            <Input v-model="bookDate" type="date" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Duration</Label>
                            <Select
                                :model-value="String(bookForm.duration_minutes)"
                                @update:model-value="
                                    (v) =>
                                        (bookForm.duration_minutes = Number(v))
                                "
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="d in durations"
                                        :key="d"
                                        :value="String(d)"
                                        >{{ d }} min</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Time slot *</Label>
                        <p
                            v-if="!bookForm.provider_id"
                            class="text-xs text-muted-foreground"
                        >
                            Select a provider to see available slots.
                        </p>
                        <p
                            v-else-if="slotsLoading"
                            class="text-xs text-muted-foreground"
                        >
                            Loading slots…
                        </p>
                        <p
                            v-else-if="slots.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No availability template for this day — pick any
                            time below.
                        </p>
                        <div v-if="slots.length" class="flex flex-wrap gap-1.5">
                            <button
                                v-for="slot in slots"
                                :key="slot.start"
                                type="button"
                                :disabled="!slot.available"
                                class="rounded-md border px-2.5 py-1 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="
                                    bookForm.scheduled_start === slot.start
                                        ? 'border-primary bg-primary/10 font-medium text-primary'
                                        : 'border-border hover:bg-muted'
                                "
                                @click="bookForm.scheduled_start = slot.start"
                            >
                                {{ slot.label }}
                            </button>
                        </div>
                        <Input
                            v-else
                            v-model="bookForm.scheduled_start"
                            type="datetime-local"
                        />
                        <InputError
                            :message="bookForm.errors.scheduled_start"
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Priority</Label>
                            <Select v-model="bookForm.priority">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in priorities"
                                        :key="p.value"
                                        :value="p.value"
                                        >{{ p.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Reason</Label>
                            <Input
                                v-model="bookForm.reason"
                                placeholder="e.g. Review"
                            />
                        </div>
                    </div>

                    <div>
                        <Button
                            type="submit"
                            :disabled="
                                bookForm.processing ||
                                !bookForm.patient_id ||
                                !bookForm.service_point_id ||
                                !bookForm.scheduled_start
                            "
                        >
                            <CalendarPlus class="size-4" />
                            Book appointment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ===== Walk-in dialog ===== -->
        <Dialog v-model:open="walkInOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Register walk-in</DialogTitle>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitWalkIn">
                    <div class="grid gap-1.5">
                        <Label>Patient *</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="patientQuery"
                                placeholder="Search by name or file number…"
                                class="pl-8"
                            />
                            <ul
                                v-if="patientResults.length"
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-border bg-popover shadow-md"
                            >
                                <li
                                    v-for="p in patientResults"
                                    :key="p.id"
                                    class="cursor-pointer px-3 py-2 text-sm hover:bg-muted"
                                    @click="pickPatient(p)"
                                >
                                    <span class="font-medium">{{
                                        p.name
                                    }}</span>
                                    <span
                                        class="ml-1 text-xs text-muted-foreground"
                                        >{{ p.file_number }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <InputError :message="walkInForm.errors.patient_id" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Clinic *</Label>
                            <Select v-model="walkInForm.service_point_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select clinic" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="s in servicePoints"
                                        :key="s.id"
                                        :value="String(s.id)"
                                        >{{ s.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="walkInForm.errors.service_point_id"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Provider</Label>
                            <Select v-model="walkInForm.provider_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Any provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in providers"
                                        :key="p.id"
                                        :value="String(p.id)"
                                        >{{ p.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Priority</Label>
                            <Select v-model="walkInForm.priority">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in priorities"
                                        :key="p.value"
                                        :value="p.value"
                                        >{{ p.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Reason</Label>
                            <Input
                                v-model="walkInForm.reason"
                                placeholder="e.g. Acute complaint"
                            />
                        </div>
                    </div>
                    <div>
                        <Button
                            type="submit"
                            :disabled="
                                walkInForm.processing ||
                                !walkInForm.patient_id ||
                                !walkInForm.service_point_id
                            "
                        >
                            <UserPlus class="size-4" />
                            Check in walk-in
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ===== Reschedule dialog ===== -->
        <Dialog
            :open="rescheduleTarget !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) rescheduleTarget = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle
                        >Reschedule —
                        {{ rescheduleTarget?.patient.name }}</DialogTitle
                    >
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitReschedule">
                    <div class="grid gap-1.5">
                        <Label>Date</Label>
                        <Input v-model="rescheduleDate" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>New time slot *</Label>
                        <p
                            v-if="slotsLoading"
                            class="text-xs text-muted-foreground"
                        >
                            Loading slots…
                        </p>
                        <div v-if="slots.length" class="flex flex-wrap gap-1.5">
                            <button
                                v-for="slot in slots"
                                :key="slot.start"
                                type="button"
                                :disabled="!slot.available"
                                class="rounded-md border px-2.5 py-1 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="
                                    rescheduleForm.scheduled_start ===
                                    slot.start
                                        ? 'border-primary bg-primary/10 font-medium text-primary'
                                        : 'border-border hover:bg-muted'
                                "
                                @click="
                                    rescheduleForm.scheduled_start = slot.start
                                "
                            >
                                {{ slot.label }}
                            </button>
                        </div>
                        <Input
                            v-else
                            v-model="rescheduleForm.scheduled_start"
                            type="datetime-local"
                        />
                        <InputError
                            :message="rescheduleForm.errors.scheduled_start"
                        />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            :disabled="
                                rescheduleForm.processing ||
                                !rescheduleForm.scheduled_start
                            "
                        >
                            <CalendarDays class="size-4" />
                            Reschedule
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
