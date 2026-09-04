<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    Ban,
    BedSingle,
    DoorOpen,
    HeartPulse,
    LogOut,
    NotebookPen,
    ReceiptText,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ObservationHistory from '@/components/observations/ObservationHistory.vue';
import ObservationSetForm from '@/components/observations/ObservationSetForm.vue';
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
import type {
    ObservationCodeDefinition,
    ObservationSet,
} from '@/types/clinical';

type Option = { value: string; label: string };

type WardOption = {
    id: number;
    name: string;
    available: number;
    available_beds: Array<{ id: number; label: string }>;
};

const props = defineProps<{
    admission: {
        id: number;
        admission_number: string;
        status: string;
        status_label: string;
        tone: string;
        is_active: boolean;
        diagnosis: string;
        reason: string | null;
        ward: string | null;
        ward_id: number | null;
        bed: string | null;
        bed_id: number | null;
        requested_by: string | null;
        requested_at: string | null;
        admitted_by: string | null;
        admitted_at: string | null;
        admitted_diff: string | null;
        attending: string | null;
        attending_id: number | null;
        days: number | null;
        discharged_by: string | null;
        discharged_at: string | null;
        discharge_type: string | null;
        discharge_type_label: string | null;
        discharge_summary: string | null;
        follow_up_at: string | null;
        cancel_reason: string | null;
        cancelled_at: string | null;
    };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex: string;
        age: number | null;
        url: string;
        phone: string | null;
        coverage_label: string;
    };
    movements: Array<{
        id: number;
        from: string | null;
        to: string | null;
        reason: string | null;
        moved_by: string | null;
        moved_at: string;
    }>;
    notes: Array<{
        id: number;
        type: string;
        type_label: string;
        note: string;
        author: string | null;
        recorded_at: string;
        recorded_diff: string;
    }>;
    observationSets: ObservationSet[];
    observationsUrl: string;
    observationCodes: ObservationCodeDefinition[];
    bill: {
        total: number;
        paid: number;
        balance: number;
        url: string | null;
    } | null;
    wards: WardOption[];
    clinicians: Array<{ id: number; name: string }>;
    noteTypes: Option[];
    dischargeTypes: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admissions', href: '/admissions' },
            { title: 'Inpatient record', href: '#' },
        ],
    },
});

// Guard failures from the service (wrong status, bed taken meanwhile) come
// back under a key that is not a form field.
const page = usePage();
const serviceError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.status,
);

const isPending = computed(() => props.admission.status === 'pending');
const isAdmitted = computed(() => props.admission.status === 'admitted');

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-primary/10 text-primary',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

function money(v: number): string {
    return '₦' + v.toLocaleString('en-NG', { minimumFractionDigits: 2 });
}

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

// --- Notes ---
const noteForm = useForm({ type: 'progress', note: '' });

function submitNote() {
    noteForm.post(`/admissions/${props.admission.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('note'),
    });
}

// --- Ward observations ---
const observationsOpen = ref(false);

// --- Bed placement (assign / transfer share one dialog) ---
const bedDialog = ref<'assign' | 'transfer' | null>(null);
const bedForm = useForm({
    ward_id: '',
    bed_id: '',
    attending_id: '',
    reason: '',
});

const bedWard = computed(
    () => props.wards.find((w) => String(w.id) === bedForm.ward_id) ?? null,
);

watch(
    () => bedForm.ward_id,
    () => {
        bedForm.bed_id = '';
    },
);

function openBedDialog(mode: 'assign' | 'transfer') {
    bedForm.reset();
    bedForm.clearErrors();
    bedForm.ward_id = props.admission.ward_id
        ? String(props.admission.ward_id)
        : '';
    bedForm.attending_id = props.admission.attending_id
        ? String(props.admission.attending_id)
        : '';
    bedDialog.value = mode;
}

function submitBed() {
    const mode = bedDialog.value;

    if (!mode) {
        return;
    }

    bedForm
        .transform((data) =>
            mode === 'assign'
                ? {
                      bed_id: data.bed_id ? Number(data.bed_id) : null,
                      attending_id: data.attending_id
                          ? Number(data.attending_id)
                          : null,
                  }
                : {
                      bed_id: data.bed_id ? Number(data.bed_id) : null,
                      reason: data.reason || null,
                  },
        )
        .post(`/admissions/${props.admission.id}/${mode}`, {
            preserveScroll: true,
            onSuccess: () => {
                bedDialog.value = null;
            },
        });
}

// --- Discharge ---
const dischargeOpen = ref(false);
const dischargeForm = useForm({
    discharge_type: 'home',
    discharge_summary: '',
    follow_up_at: '',
});

function submitDischarge() {
    dischargeForm
        .transform((data) => ({
            ...data,
            follow_up_at: data.follow_up_at || null,
        }))
        .post(`/admissions/${props.admission.id}/discharge`, {
            preserveScroll: true,
            onSuccess: () => {
                dischargeOpen.value = false;
            },
        });
}

// --- Cancel order ---
const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });

function submitCancel() {
    cancelForm.post(`/admissions/${props.admission.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
        },
    });
}

const details = computed(() => [
    { label: 'Admitting diagnosis', value: props.admission.diagnosis },
    { label: 'Reason / plan', value: props.admission.reason },
    {
        label: 'Ordered',
        value: props.admission.requested_at
            ? `${props.admission.requested_at} by ${props.admission.requested_by ?? '—'}`
            : null,
    },
    {
        label: 'Admitted',
        value: props.admission.admitted_at
            ? `${props.admission.admitted_at} by ${props.admission.admitted_by ?? '—'}`
            : null,
    },
    { label: 'Attending clinician', value: props.admission.attending },
]);
</script>

<template>
    <Head :title="`${patient.name} · ${admission.admission_number}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link href="/admissions" aria-label="Back to admissions">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                    >{{ patient.initials }}</span
                >
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ patient.name }}
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        <span class="font-mono">{{ patient.file_number }}</span>
                        · {{ patient.sex
                        }}{{
                            patient.age !== null
                                ? ' · ' + patient.age + 'y'
                                : ''
                        }}
                        · {{ patient.coverage_label }}
                        <span v-if="patient.phone"> · {{ patient.phone }}</span>
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="toneClass(admission.tone)"
                            >{{ admission.status_label }}</span
                        >
                        <span class="font-mono text-xs text-muted-foreground">{{
                            admission.admission_number
                        }}</span>
                        <span
                            v-if="admission.ward"
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            <BedSingle class="size-3" />
                            {{ admission.ward }}
                            <span v-if="admission.bed"
                                >· {{ admission.bed }}</span
                            >
                        </span>
                        <span
                            v-if="isAdmitted && admission.days"
                            class="text-xs text-muted-foreground"
                            >Day {{ admission.days }}</span
                        >
                        <Link
                            :href="patient.url"
                            class="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            >Patient profile</Link
                        >
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <template v-if="isPending">
                    <Button variant="outline" @click="cancelOpen = true">
                        <Ban class="size-4" />
                        Cancel order
                    </Button>
                    <Button @click="openBedDialog('assign')">
                        <DoorOpen class="size-4" />
                        Assign bed
                    </Button>
                </template>
                <template v-else-if="isAdmitted">
                    <Button
                        variant="outline"
                        @click="openBedDialog('transfer')"
                    >
                        <ArrowRightLeft class="size-4" />
                        Transfer
                    </Button>
                    <Button @click="dischargeOpen = true">
                        <LogOut class="size-4" />
                        Discharge
                    </Button>
                </template>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- Admission details -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Admission</h2>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <template v-for="d in details" :key="d.label">
                            <div v-if="d.value">
                                <dt class="text-xs text-muted-foreground">
                                    {{ d.label }}
                                </dt>
                                <dd class="mt-0.5 text-sm whitespace-pre-line">
                                    {{ d.value }}
                                </dd>
                            </div>
                        </template>
                    </dl>

                    <div
                        v-if="admission.status === 'discharged'"
                        class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4"
                    >
                        <p class="text-sm font-medium">
                            {{ admission.discharge_type_label }}
                            <span class="font-normal text-muted-foreground">
                                · {{ admission.discharged_at }} by
                                {{ admission.discharged_by ?? '—' }} ·
                                {{ admission.days }}
                                {{
                                    admission.days === 1 ? 'day' : 'days'
                                }}</span
                            >
                        </p>
                        <p
                            v-if="admission.discharge_summary"
                            class="mt-2 text-sm whitespace-pre-line"
                        >
                            {{ admission.discharge_summary }}
                        </p>
                        <p
                            v-if="admission.follow_up_at"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Follow-up on {{ admission.follow_up_at }}
                        </p>
                    </div>
                    <div
                        v-else-if="admission.status === 'cancelled'"
                        class="mt-4 rounded-lg border border-border bg-muted/40 p-4 text-sm"
                    >
                        Order cancelled {{ admission.cancelled_at }}.
                        <span v-if="admission.cancel_reason">
                            {{ admission.cancel_reason }}</span
                        >
                    </div>
                </section>

                <!-- Ward notes -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <NotebookPen class="size-4 text-muted-foreground" />
                        Ward notes
                    </h2>

                    <form
                        v-if="admission.is_active"
                        class="mb-4 grid gap-2"
                        @submit.prevent="submitNote"
                    >
                        <div class="flex gap-2">
                            <Select v-model="noteForm.type">
                                <SelectTrigger class="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="t in noteTypes"
                                        :key="t.value"
                                        :value="t.value"
                                        >{{ t.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <textarea
                            v-model="noteForm.note"
                            :class="textareaClass"
                            rows="3"
                            placeholder="Findings, progress, plan…"
                        ></textarea>
                        <InputError :message="noteForm.errors.note" />
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    noteForm.processing || !noteForm.note.trim()
                                "
                            >
                                <Spinner v-if="noteForm.processing" />
                                Save note
                            </Button>
                        </div>
                    </form>

                    <p
                        v-if="!notes.length"
                        class="text-sm text-muted-foreground"
                    >
                        No notes yet.
                    </p>
                    <ol v-else class="divide-y divide-border">
                        <li v-for="n in notes" :key="n.id" class="py-3">
                            <div
                                class="flex flex-wrap items-baseline justify-between gap-2 text-xs text-muted-foreground"
                            >
                                <span>
                                    <span class="font-medium text-foreground">{{
                                        n.type_label
                                    }}</span>
                                    · {{ n.author ?? '—' }}
                                </span>
                                <span :title="n.recorded_at">{{
                                    n.recorded_diff
                                }}</span>
                            </div>
                            <p class="mt-1 text-sm whitespace-pre-line">
                                {{ n.note }}
                            </p>
                        </li>
                    </ol>
                </section>

                <!-- Observations -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <HeartPulse class="size-4 text-muted-foreground" />
                            Ward observations
                        </h2>
                        <Button
                            v-if="isAdmitted"
                            size="sm"
                            variant="outline"
                            @click="observationsOpen = !observationsOpen"
                        >
                            {{
                                observationsOpen
                                    ? 'Close'
                                    : 'Record observations'
                            }}
                        </Button>
                    </div>

                    <div
                        v-if="observationsOpen && isAdmitted"
                        class="mb-4 rounded-lg border border-border p-4"
                    >
                        <ObservationSetForm
                            :action="observationsUrl"
                            :codes="observationCodes"
                            :context="{ admission_id: admission.id }"
                            @saved="observationsOpen = false"
                            @cancel="observationsOpen = false"
                        />
                    </div>

                    <ObservationHistory
                        :sets="observationSets"
                        empty-text="No ward observations recorded."
                    />
                </section>
            </div>

            <aside class="flex flex-col gap-6">
                <!-- Bill -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <ReceiptText class="size-4 text-muted-foreground" />
                        Running bill
                    </h2>
                    <template v-if="bill">
                        <dl class="grid grid-cols-2 gap-y-1.5 text-sm">
                            <dt class="text-muted-foreground">Charges</dt>
                            <dd class="text-right tabular-nums">
                                {{ money(bill.total) }}
                            </dd>
                            <dt class="text-muted-foreground">Paid</dt>
                            <dd class="text-right tabular-nums">
                                {{ money(bill.paid) }}
                            </dd>
                            <dt class="font-medium">Balance</dt>
                            <dd
                                class="text-right font-semibold tabular-nums"
                                :class="
                                    bill.balance > 0 ? 'text-amber-600' : ''
                                "
                            >
                                {{ money(bill.balance) }}
                            </dd>
                        </dl>
                        <Button
                            v-if="bill.url"
                            as-child
                            size="sm"
                            variant="outline"
                            class="mt-3 w-full"
                        >
                            <Link :href="bill.url">Open bill</Link>
                        </Button>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Bed days are billed at the ward rate on discharge.
                        </p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        No open bill for this visit.
                    </p>
                </section>

                <!-- Bed history -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Bed history</h2>
                    <p
                        v-if="!movements.length"
                        class="text-sm text-muted-foreground"
                    >
                        Not yet placed in a bed.
                    </p>
                    <ol v-else class="relative ml-2 border-l border-border">
                        <li
                            v-for="m in movements"
                            :key="m.id"
                            class="mb-4 ml-4 last:mb-0"
                        >
                            <span
                                class="absolute -left-[5px] mt-1.5 size-2.5 rounded-full border border-background bg-primary"
                            ></span>
                            <p class="text-sm font-medium">{{ m.to ?? '—' }}</p>
                            <p
                                v-if="m.from"
                                class="text-xs text-muted-foreground"
                            >
                                from {{ m.from }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ m.moved_at }} · {{ m.moved_by ?? '—' }}
                            </p>
                            <p
                                v-if="m.reason"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ m.reason }}
                            </p>
                        </li>
                    </ol>
                </section>
            </aside>
        </div>

        <!-- Assign / transfer dialog -->
        <Dialog
            :open="bedDialog !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) bedDialog = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            bedDialog === 'assign'
                                ? 'Assign a bed'
                                : 'Transfer patient'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            bedDialog === 'assign'
                                ? 'Place the patient in a free bed to admit them.'
                                : 'Move the patient to another bed. The current bed is freed.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitBed">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Ward *</Label>
                            <Select v-model="bedForm.ward_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Choose ward" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="w in wards"
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
                                v-model="bedForm.bed_id"
                                :disabled="!bedWard"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Choose bed" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="b in bedWard?.available_beds ??
                                        []"
                                        :key="b.id"
                                        :value="String(b.id)"
                                        >{{ b.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="bedForm.errors.bed_id" />
                        </div>
                    </div>
                    <div v-if="bedDialog === 'assign'" class="grid gap-1.5">
                        <Label>Attending clinician</Label>
                        <Select v-model="bedForm.attending_id">
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
                    <div v-else class="grid gap-1.5">
                        <Label for="transfer-reason">Reason</Label>
                        <Input
                            id="transfer-reason"
                            v-model="bedForm.reason"
                            placeholder="e.g. Needs closer monitoring"
                        />
                        <InputError :message="bedForm.errors.reason" />
                    </div>
                    <InputError :message="serviceError" />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="bedDialog = null"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="bedForm.processing || !bedForm.bed_id"
                        >
                            <Spinner v-if="bedForm.processing" />
                            {{
                                bedDialog === 'assign'
                                    ? 'Admit to bed'
                                    : 'Transfer'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Discharge dialog -->
        <Dialog v-model:open="dischargeOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Discharge {{ patient.name }}</DialogTitle>
                    <DialogDescription>
                        Frees {{ admission.ward }} · {{ admission.bed }} and
                        bills {{ admission.days }}
                        {{ admission.days === 1 ? 'day' : 'days' }} at the ward
                        rate.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitDischarge">
                    <div class="grid gap-1.5">
                        <Label>Outcome *</Label>
                        <Select v-model="dischargeForm.discharge_type">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in dischargeTypes"
                                    :key="t.value"
                                    :value="t.value"
                                    >{{ t.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="dischargeForm.errors.discharge_type"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="discharge-summary">Discharge summary</Label>
                        <textarea
                            id="discharge-summary"
                            v-model="dischargeForm.discharge_summary"
                            :class="textareaClass"
                            rows="5"
                            placeholder="Course on the ward, condition at discharge, medications and instructions"
                        ></textarea>
                        <InputError
                            :message="dischargeForm.errors.discharge_summary"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="discharge-follow-up">Follow-up date</Label>
                        <Input
                            id="discharge-follow-up"
                            v-model="dischargeForm.follow_up_at"
                            type="date"
                        />
                        <InputError
                            :message="dischargeForm.errors.follow_up_at"
                        />
                    </div>
                    <InputError :message="serviceError" />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="dischargeOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="dischargeForm.processing"
                        >
                            <Spinner v-if="dischargeForm.processing" />
                            <LogOut v-else class="size-4" />
                            Discharge
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Cancel order dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Cancel admission order</DialogTitle>
                    <DialogDescription>
                        The order is withdrawn and the patient stays an
                        outpatient.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitCancel">
                    <div class="grid gap-1.5">
                        <Label for="cancel-reason">Reason</Label>
                        <Input
                            id="cancel-reason"
                            v-model="cancelForm.reason"
                            placeholder="Optional"
                        />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <InputError :message="serviceError" />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="cancelOpen = false"
                            >Keep order</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="cancelForm.processing"
                        >
                            <Spinner v-if="cancelForm.processing" />
                            Cancel order
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
