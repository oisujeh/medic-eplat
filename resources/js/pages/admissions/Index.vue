<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    BedDouble,
    BedSingle,
    Clock,
    DoorOpen,
    Plus,
    Search,
    UserPlus,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type PatientCard = {
    id: number;
    name: string;
    initials: string;
    file_number: string;
    sex: string;
    age: number | null;
    url: string;
};

type WardCard = {
    id: number;
    name: string;
    code: string;
    type: string;
    type_label: string;
    is_active: boolean;
    url: string;
    total: number;
    available: number;
    occupied: number;
    out_of_service: number;
    available_beds: Array<{ id: number; label: string }>;
};

type PendingAdmission = {
    id: number;
    admission_number: string;
    diagnosis: string;
    requested_ward: string | null;
    requested_ward_id: number | null;
    requested_by: string | null;
    requested_diff: string | null;
    attending: string | null;
    attending_id: number | null;
    url: string;
    patient: PatientCard;
};

type Inpatient = {
    id: number;
    admission_number: string;
    diagnosis: string;
    ward: string | null;
    ward_id: number | null;
    bed: string | null;
    attending: string | null;
    admitted_at: string | null;
    admitted_diff: string | null;
    days: number | null;
    url: string;
    patient: PatientCard;
};

type Option = { value: string; label: string };

const props = defineProps<{
    wards: WardCard[];
    pending: PendingAdmission[];
    inpatients: Inpatient[];
    clinicians: Array<{ id: number; name: string }>;
    wardTypes: Option[];
    bedCharges: Array<{ id: number; name: string; price: number }>;
    preselected: PatientCard | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admissions', href: '/admissions' }],
    },
});

// Guard failures from the service (e.g. a bed taken meanwhile) come back
// under keys that are not form fields.
const page = usePage();
const serviceError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.status,
);

const totals = computed(() => ({
    beds: props.wards.reduce((n, w) => n + w.total, 0),
    occupied: props.wards.reduce((n, w) => n + w.occupied, 0),
    available: props.wards.reduce((n, w) => n + w.available, 0),
    outOfService: props.wards.reduce((n, w) => n + w.out_of_service, 0),
}));

function occupancyPercent(ward: WardCard): number {
    const usable = ward.total - ward.out_of_service;

    return usable > 0 ? Math.round((ward.occupied / usable) * 100) : 0;
}

// --- Inpatient list filter ---
const wardFilter = ref('all');
const filteredInpatients = computed(() =>
    wardFilter.value === 'all'
        ? props.inpatients
        : props.inpatients.filter(
              (a) => String(a.ward_id) === wardFilter.value,
          ),
);

// --- Admit patient dialog ---
const admitOpen = ref(false);
const patientQuery = ref('');
const patientResults = ref<PatientCard[]>([]);
const selectedPatient = ref<PatientCard | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(patientQuery, (q) => {
    clearTimeout(searchTimer);

    if (q.trim().length < 2) {
        patientResults.value = [];

        return;
    }

    searchTimer = setTimeout(async () => {
        const res = await fetch(
            `/admissions/patient-search?q=${encodeURIComponent(q)}`,
            { headers: { Accept: 'application/json' } },
        );
        patientResults.value = (await res.json()).patients ?? [];
    }, 250);
});

const admitForm = useForm({
    patient_id: '' as string | number,
    admitting_diagnosis: '',
    reason: '',
    ward_id: '',
    bed_id: '',
    attending_id: '',
});

const admitWard = computed(
    () => props.wards.find((w) => String(w.id) === admitForm.ward_id) ?? null,
);

// A bed only makes sense inside the chosen ward.
watch(
    () => admitForm.ward_id,
    () => {
        admitForm.bed_id = '';
    },
);

function choosePatient(p: PatientCard) {
    selectedPatient.value = p;
    admitForm.patient_id = p.id;
    patientResults.value = [];
    patientQuery.value = '';
}

function openAdmit(patient: PatientCard | null = null) {
    admitForm.reset();
    admitForm.clearErrors();
    selectedPatient.value = null;
    patientQuery.value = '';

    if (patient) {
        choosePatient(patient);
    }

    admitOpen.value = true;
}

function submitAdmit() {
    admitForm
        .transform((data) => ({
            ...data,
            ward_id: data.ward_id ? Number(data.ward_id) : null,
            bed_id: data.bed_id ? Number(data.bed_id) : null,
            attending_id: data.attending_id ? Number(data.attending_id) : null,
        }))
        .post('/admissions', {
            preserveScroll: true,
            onSuccess: () => {
                admitOpen.value = false;
            },
        });
}

onMounted(() => {
    if (props.preselected) {
        openAdmit(props.preselected);
    }
});

// --- Assign bed dialog (for pending orders) ---
const assigning = ref<PendingAdmission | null>(null);
const assignForm = useForm({
    ward_id: '',
    bed_id: '',
    attending_id: '',
});

const assignWard = computed(
    () => props.wards.find((w) => String(w.id) === assignForm.ward_id) ?? null,
);

watch(
    () => assignForm.ward_id,
    () => {
        assignForm.bed_id = '';
    },
);

function openAssign(admission: PendingAdmission) {
    assigning.value = admission;
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.ward_id = admission.requested_ward_id
        ? String(admission.requested_ward_id)
        : '';
    assignForm.attending_id = admission.attending_id
        ? String(admission.attending_id)
        : '';
}

function submitAssign() {
    if (!assigning.value) {
        return;
    }

    assignForm
        .transform((data) => ({
            bed_id: data.bed_id ? Number(data.bed_id) : null,
            attending_id: data.attending_id ? Number(data.attending_id) : null,
        }))
        .post(`/admissions/${assigning.value.id}/assign`, {
            preserveScroll: true,
            onSuccess: () => {
                assigning.value = null;
            },
        });
}

// --- New ward dialog ---
const wardOpen = ref(false);
const wardForm = useForm({
    name: '',
    code: '',
    type: 'general',
    bed_service_charge_id: '',
    description: '',
    initial_beds: 10,
    bed_prefix: 'Bed',
});

function submitWard() {
    wardForm
        .transform((data) => ({
            ...data,
            bed_service_charge_id: data.bed_service_charge_id
                ? Number(data.bed_service_charge_id)
                : null,
        }))
        .post('/admissions/wards', {
            onSuccess: () => {
                wardOpen.value = false;
                wardForm.reset();
            },
        });
}

function money(v: number): string {
    return '₦' + v.toLocaleString('en-NG', { minimumFractionDigits: 0 });
}

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Admissions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Admissions &amp; wards
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Bed occupancy across the facility, admission orders awaiting
                    a bed, and every patient currently on a ward.
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="wardOpen = true">
                    <Plus class="size-4" />
                    New ward
                </Button>
                <Button @click="openAdmit()">
                    <UserPlus class="size-4" />
                    Admit patient
                </Button>
            </div>
        </div>

        <!-- Facility-wide bed summary -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Beds</p>
                <p class="mt-1 text-2xl font-semibold">{{ totals.beds }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Occupied</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ totals.occupied }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Available</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">
                    {{ totals.available }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Awaiting a bed</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="pending.length ? 'text-amber-600' : ''"
                >
                    {{ pending.length }}
                </p>
            </div>
        </div>

        <!-- Wards -->
        <section>
            <h2 class="mb-3 text-sm font-semibold">Wards</h2>
            <div
                v-if="!wards.length"
                class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
            >
                No wards yet. Create the first ward to start admitting patients.
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="ward in wards"
                    :key="ward.id"
                    :href="ward.url"
                    class="rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40"
                    :class="{ 'opacity-60': !ward.is_active }"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ ward.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                <span class="font-mono">{{ ward.code }}</span>
                                · {{ ward.type_label }}
                                <span v-if="!ward.is_active"> · inactive</span>
                            </p>
                        </div>
                        <BedDouble class="size-4 text-muted-foreground" />
                    </div>
                    <div class="mt-3">
                        <div
                            class="flex items-baseline justify-between text-xs"
                        >
                            <span>
                                <span class="text-lg font-semibold">{{
                                    ward.occupied
                                }}</span>
                                <span class="text-muted-foreground">
                                    / {{ ward.total }} occupied</span
                                >
                            </span>
                            <span class="text-muted-foreground"
                                >{{ occupancyPercent(ward) }}%</span
                            >
                        </div>
                        <div
                            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="
                                    occupancyPercent(ward) >= 90
                                        ? 'bg-red-500'
                                        : occupancyPercent(ward) >= 70
                                          ? 'bg-amber-500'
                                          : 'bg-emerald-500'
                                "
                                :style="{
                                    width: occupancyPercent(ward) + '%',
                                }"
                            ></div>
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{ ward.available }} free
                            <span v-if="ward.out_of_service">
                                · {{ ward.out_of_service }} out of service</span
                            >
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <!-- Current inpatients -->
            <section class="min-w-0">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">
                        Inpatients
                        <span class="font-normal text-muted-foreground"
                            >({{ filteredInpatients.length }})</span
                        >
                    </h2>
                    <Select v-model="wardFilter">
                        <SelectTrigger class="h-8 w-48 text-xs">
                            <SelectValue placeholder="All wards" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All wards</SelectItem>
                            <SelectItem
                                v-for="w in wards"
                                :key="w.id"
                                :value="String(w.id)"
                                >{{ w.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>

                <div
                    v-if="!filteredInpatients.length"
                    class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
                >
                    No patients on the ward.
                </div>
                <div
                    v-else
                    class="overflow-x-auto rounded-xl border border-border bg-card"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-2.5 font-medium">Patient</th>
                                <th class="px-4 py-2.5 font-medium">Bed</th>
                                <th class="px-4 py-2.5 font-medium">
                                    Diagnosis
                                </th>
                                <th class="px-4 py-2.5 font-medium">
                                    Attending
                                </th>
                                <th class="px-4 py-2.5 font-medium">
                                    Admitted
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Days
                                </th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="a in filteredInpatients" :key="a.id">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                                            >{{ a.patient.initials }}</span
                                        >
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">
                                                {{ a.patient.name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                <span class="font-mono">{{
                                                    a.patient.file_number
                                                }}</span>
                                                · {{ a.patient.sex
                                                }}{{
                                                    a.patient.age !== null
                                                        ? ' · ' +
                                                          a.patient.age +
                                                          'y'
                                                        : ''
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p>{{ a.ward }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ a.bed }}
                                    </p>
                                </td>
                                <td class="max-w-56 px-4 py-2.5">
                                    <p class="truncate" :title="a.diagnosis">
                                        {{ a.diagnosis }}
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    {{ a.attending ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p>{{ a.admitted_at }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ a.admitted_diff }}
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ a.days ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="a.url">Open</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Awaiting a bed -->
            <aside>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <Clock class="size-4 text-amber-600" />
                    Awaiting a bed
                </h2>
                <div
                    v-if="!pending.length"
                    class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                >
                    No admission orders waiting. Orders from the consultation
                    room appear here for the ward to place.
                </div>
                <div v-else class="flex flex-col gap-3">
                    <div
                        v-for="p in pending"
                        :key="p.id"
                        class="rounded-xl border border-amber-500/30 bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <Link
                                    :href="p.url"
                                    class="font-medium hover:underline"
                                    >{{ p.patient.name }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-mono">{{
                                        p.patient.file_number
                                    }}</span>
                                    · {{ p.patient.sex
                                    }}{{
                                        p.patient.age !== null
                                            ? ' · ' + p.patient.age + 'y'
                                            : ''
                                    }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                                >{{ p.requested_diff }}</span
                            >
                        </div>
                        <p class="mt-2 text-sm">{{ p.diagnosis }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <span v-if="p.requested_ward"
                                >For {{ p.requested_ward }} · </span
                            >ordered by {{ p.requested_by ?? '—' }}
                        </p>
                        <Button
                            size="sm"
                            class="mt-3 w-full"
                            @click="openAssign(p)"
                        >
                            <BedSingle class="size-4" />
                            Assign bed
                        </Button>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Admit patient dialog -->
        <Dialog v-model:open="admitOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Admit patient</DialogTitle>
                    <DialogDescription>
                        Choose a bed to admit straight away, or leave the bed
                        empty to place the order in the waiting list.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitAdmit">
                    <div class="grid gap-1.5">
                        <Label>Patient *</Label>
                        <div
                            v-if="selectedPatient"
                            class="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <span>
                                <span class="font-medium">{{
                                    selectedPatient.name
                                }}</span>
                                <span class="text-muted-foreground">
                                    ·
                                    <span class="font-mono">{{
                                        selectedPatient.file_number
                                    }}</span></span
                                >
                            </span>
                            <button
                                type="button"
                                class="text-xs text-muted-foreground hover:underline"
                                @click="
                                    selectedPatient = null;
                                    admitForm.patient_id = '';
                                "
                            >
                                Change
                            </button>
                        </div>
                        <div v-else class="relative">
                            <Search
                                class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                            />
                            <Input
                                v-model="patientQuery"
                                class="pl-8"
                                placeholder="Search by name or file number"
                                autocomplete="off"
                            />
                            <ul
                                v-if="patientResults.length"
                                class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-popover text-sm shadow-md"
                            >
                                <li
                                    v-for="p in patientResults"
                                    :key="p.id"
                                    class="cursor-pointer px-3 py-2 hover:bg-muted"
                                    @click="choosePatient(p)"
                                >
                                    <span class="font-medium">{{
                                        p.name
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        · {{ p.file_number }} · {{ p.sex
                                        }}{{
                                            p.age !== null
                                                ? ' · ' + p.age + 'y'
                                                : ''
                                        }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <InputError :message="admitForm.errors.patient_id" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="admit-diagnosis"
                            >Admitting diagnosis *</Label
                        >
                        <textarea
                            id="admit-diagnosis"
                            v-model="admitForm.admitting_diagnosis"
                            :class="textareaClass"
                            rows="2"
                            placeholder="e.g. Severe malaria with anaemia"
                        ></textarea>
                        <InputError
                            :message="admitForm.errors.admitting_diagnosis"
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="admit-reason">Reason / plan</Label>
                        <textarea
                            id="admit-reason"
                            v-model="admitForm.reason"
                            :class="textareaClass"
                            rows="2"
                            placeholder="Why the patient needs a bed, and the initial plan"
                        ></textarea>
                        <InputError :message="admitForm.errors.reason" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Ward</Label>
                            <Select v-model="admitForm.ward_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Choose ward" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="w in wards.filter(
                                            (x) => x.is_active,
                                        )"
                                        :key="w.id"
                                        :value="String(w.id)"
                                        >{{ w.name }} ({{
                                            w.available
                                        }}
                                        free)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="admitForm.errors.ward_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Bed</Label>
                            <Select
                                v-model="admitForm.bed_id"
                                :disabled="!admitWard"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        :placeholder="
                                            admitWard
                                                ? 'Assign later'
                                                : 'Choose a ward first'
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="b in admitWard?.available_beds ??
                                        []"
                                        :key="b.id"
                                        :value="String(b.id)"
                                        >{{ b.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="admitForm.errors.bed_id" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Attending clinician</Label>
                        <Select v-model="admitForm.attending_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Not yet assigned" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in clinicians"
                                    :key="c.id"
                                    :value="String(c.id)"
                                    >{{ c.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="admitForm.errors.attending_id" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="admitOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="admitForm.processing || !selectedPatient"
                        >
                            <Spinner v-if="admitForm.processing" />
                            {{
                                admitForm.bed_id
                                    ? 'Admit to bed'
                                    : 'Order admission'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Assign bed dialog -->
        <Dialog
            :open="assigning !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) assigning = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assign a bed</DialogTitle>
                    <DialogDescription>
                        {{ assigning?.patient.name }} ·
                        {{ assigning?.diagnosis }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitAssign">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Ward *</Label>
                            <Select v-model="assignForm.ward_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Choose ward" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="w in wards.filter(
                                            (x) => x.is_active,
                                        )"
                                        :key="w.id"
                                        :value="String(w.id)"
                                        >{{ w.name }} ({{
                                            w.available
                                        }}
                                        free)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Bed *</Label>
                            <Select
                                v-model="assignForm.bed_id"
                                :disabled="!assignWard"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Choose bed" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="b in assignWard?.available_beds ??
                                        []"
                                        :key="b.id"
                                        :value="String(b.id)"
                                        >{{ b.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="assignForm.errors.bed_id" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Attending clinician</Label>
                        <Select v-model="assignForm.attending_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Not yet assigned" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in clinicians"
                                    :key="c.id"
                                    :value="String(c.id)"
                                    >{{ c.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <InputError :message="serviceError" />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="assigning = null"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="
                                assignForm.processing || !assignForm.bed_id
                            "
                        >
                            <Spinner v-if="assignForm.processing" />
                            <DoorOpen v-else class="size-4" />
                            Admit to bed
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- New ward dialog -->
        <Dialog v-model:open="wardOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New ward</DialogTitle>
                    <DialogDescription>
                        Beds are numbered automatically; you can add more or
                        take beds out of service from the ward's board.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submitWard"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="ward-name">Ward name *</Label>
                        <Input
                            id="ward-name"
                            v-model="wardForm.name"
                            placeholder="e.g. Female Medical Ward"
                        />
                        <InputError :message="wardForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ward-code">Code *</Label>
                        <Input
                            id="ward-code"
                            v-model="wardForm.code"
                            class="font-mono uppercase"
                            placeholder="FMW"
                            maxlength="20"
                        />
                        <InputError :message="wardForm.errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Type *</Label>
                        <Select v-model="wardForm.type">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in wardTypes"
                                    :key="t.value"
                                    :value="t.value"
                                    >{{ t.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="wardForm.errors.type" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Daily bed charge</Label>
                        <Select v-model="wardForm.bed_service_charge_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="No bed charge" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in bedCharges"
                                    :key="c.id"
                                    :value="String(c.id)"
                                    >{{ c.name }} ·
                                    {{ money(c.price) }}/day</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Billed per calendar day on discharge, from the fee
                            schedule.
                        </p>
                        <InputError
                            :message="wardForm.errors.bed_service_charge_id"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ward-beds">Beds to create</Label>
                        <Input
                            id="ward-beds"
                            v-model.number="wardForm.initial_beds"
                            type="number"
                            min="0"
                            max="200"
                        />
                        <InputError :message="wardForm.errors.initial_beds" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ward-prefix">Bed label prefix</Label>
                        <Input
                            id="ward-prefix"
                            v-model="wardForm.bed_prefix"
                            placeholder="Bed"
                        />
                        <InputError :message="wardForm.errors.bed_prefix" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="ward-description">Description</Label>
                        <Input
                            id="ward-description"
                            v-model="wardForm.description"
                            placeholder="Optional"
                        />
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="wardOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="wardForm.processing">
                            <Spinner v-if="wardForm.processing" />
                            Create ward
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
