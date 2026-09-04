<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Banknote,
    FileCheck,
    Pencil,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { naira } from '@/lib/money';

type Line = {
    id: number;
    source: string;
    description: string;
    quantity: number;
    gross_amount: number;
    amount: number;
    copay_amount: number;
    payer_amount: number;
    is_covered: boolean;
    remark: string | null;
};

const props = defineProps<{
    claim: {
        id: number;
        claim_number: string;
        status: string;
        status_label: string;
        tone: string;
        is_draft: boolean;
        is_outstanding: boolean;
        is_open: boolean;
        enrollee_number: string | null;
        plan: string | null;
        service_date: string;
        diagnosis: string | null;
        authorization_code: string | null;
        authorized_at: string | null;
        authorization_note: string | null;
        gross_amount: number;
        discount_amount: number;
        copay_amount: number;
        payer_amount: number;
        approved_amount: number | null;
        paid_amount: number;
        outstanding_amount: number;
        shortfall_amount: number;
        rejection_reason: string | null;
        remitted_at: string | null;
        remittance_reference: string | null;
        notes: string | null;
        created_by: string | null;
        created_at: string | null;
        submitted_by: string | null;
        submitted_at: string | null;
        batch: { batch_number: string; url: string } | null;
        bill_url: string | null;
        bill_balance: number;
    };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex: string;
        age: number | null;
        phone: string | null;
        hmo_expires_at: string | null;
        hmo_expired: boolean;
        url: string;
    };
    payer: {
        id: number;
        name: string;
        code: string;
        type_label: string;
        discount_percent: number;
        drug_copay_percent: number;
    };
    lines: Line[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Claims', href: '/claims' },
            { title: 'Claim', href: '#' },
        ],
    },
});

const page = usePage();
const serviceError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.status,
);

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-primary/10 text-primary',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        red: 'bg-red-500/10 text-red-700 dark:text-red-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

const sourceLabels: Record<string, string> = {
    consultation: 'Consultation',
    pharmacy: 'Pharmacy',
    laboratory: 'Laboratory',
    admission: 'Admission',
    procedure: 'Procedure',
    other: 'Other',
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

// --- Line editing (draft only) ---
const editing = ref<Line | null>(null);
const lineForm = useForm({
    amount: '' as string | number,
    copay_amount: '' as string | number,
    is_covered: true,
    remark: '',
});

function openLine(line: Line) {
    editing.value = line;
    lineForm.reset();
    lineForm.clearErrors();
    lineForm.amount = line.amount;
    lineForm.copay_amount = line.copay_amount;
    lineForm.is_covered = line.is_covered;
    lineForm.remark = line.remark ?? '';
}

function saveLine() {
    if (!editing.value) {
        return;
    }

    lineForm
        .transform((data) => ({
            amount: data.is_covered ? Number(data.amount) : null,
            copay_amount: data.is_covered ? Number(data.copay_amount) : null,
            is_covered: data.is_covered,
            remark: data.remark || null,
        }))
        .patch(`/claims/${props.claim.id}/lines/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
            },
        });
}

// --- Authorisation ---
const authOpen = ref(false);
const authForm = useForm({
    authorization_code: props.claim.authorization_code ?? '',
    authorized_at: '',
    authorization_note: props.claim.authorization_note ?? '',
});

function saveAuth() {
    authForm.post(`/claims/${props.claim.id}/authorization`, {
        preserveScroll: true,
        onSuccess: () => {
            authOpen.value = false;
        },
    });
}

// --- Submit / discard ---
const submitting = ref(false);

function submitClaim() {
    submitting.value = true;
    router.post(
        `/claims/${props.claim.id}/submit`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

const discardOpen = ref(false);
const discarding = ref(false);

function discard() {
    discarding.value = true;
    router.delete(`/claims/${props.claim.id}`, {
        onFinish: () => {
            discarding.value = false;
        },
    });
}

// --- Remittance ---
const remitOpen = ref(false);
const remitForm = useForm({
    approved_amount: props.claim.approved_amount ?? props.claim.payer_amount,
    paid_amount: props.claim.outstanding_amount,
    reference: '',
    paid_at: '',
    note: '',
});

function saveRemit() {
    remitForm
        .transform((data) => ({
            ...data,
            paid_at: data.paid_at || null,
            reference: data.reference || null,
            note: data.note || null,
        }))
        .post(`/claims/${props.claim.id}/remit`, {
            preserveScroll: true,
            onSuccess: () => {
                remitOpen.value = false;
            },
        });
}

// --- Rejection ---
const rejectOpen = ref(false);
const rejectForm = useForm({ reason: '' });

function saveReject() {
    rejectForm.post(`/claims/${props.claim.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`${claim.claim_number} · ${patient.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link href="/claims" aria-label="Back to claims">
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
                        <span v-if="patient.phone"> · {{ patient.phone }}</span>
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="toneClass(claim.tone)"
                            >{{ claim.status_label }}</span
                        >
                        <span class="font-mono text-xs text-muted-foreground">{{
                            claim.claim_number
                        }}</span>
                        <span
                            class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{ payer.name }}</span
                        >
                        <span
                            v-if="claim.authorization_code"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                        >
                            <ShieldCheck class="size-3" />
                            {{ claim.authorization_code }}
                        </span>
                        <Link
                            :href="patient.url"
                            class="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            >Patient profile</Link
                        >
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <template v-if="claim.is_draft">
                    <Button variant="outline" @click="discardOpen = true">
                        <Trash2 class="size-4" />
                        Discard
                    </Button>
                    <Button :disabled="submitting" @click="submitClaim">
                        <Spinner v-if="submitting" />
                        <FileCheck v-else class="size-4" />
                        Submit claim
                    </Button>
                </template>
                <template v-else-if="claim.is_outstanding">
                    <Button variant="outline" @click="rejectOpen = true">
                        <Ban class="size-4" />
                        Mark rejected
                    </Button>
                    <Button @click="remitOpen = true">
                        <Banknote class="size-4" />
                        Record remittance
                    </Button>
                </template>
            </div>
        </div>

        <InputError v-if="serviceError" :message="serviceError" />

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- Lines -->
                <section
                    class="overflow-x-auto rounded-xl border border-border bg-card"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-2.5 font-medium">Service</th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Facility price
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Tariff
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Co-pay
                                </th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Payer
                                </th>
                                <th
                                    v-if="claim.is_draft"
                                    class="px-4 py-2.5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="line in lines"
                                :key="line.id"
                                :class="{ 'opacity-60': !line.is_covered }"
                            >
                                <td class="px-4 py-2.5">
                                    <p>{{ line.description }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            sourceLabels[line.source] ??
                                            line.source
                                        }}
                                        <span v-if="!line.is_covered">
                                            · not covered</span
                                        >
                                        <span v-if="line.remark">
                                            · {{ line.remark }}</span
                                        >
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ naira(line.gross_amount) }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ naira(line.amount) }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ naira(line.copay_amount) }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-medium tabular-nums"
                                >
                                    {{ naira(line.payer_amount) }}
                                </td>
                                <td
                                    v-if="claim.is_draft"
                                    class="px-2 py-2.5 text-right"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        aria-label="Edit line"
                                        @click="openLine(line)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-border text-sm">
                            <tr>
                                <td class="px-4 py-2.5 font-medium">Totals</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ naira(claim.gross_amount) }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{
                                        naira(
                                            claim.gross_amount -
                                                claim.discount_amount,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ naira(claim.copay_amount) }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right font-semibold tabular-nums"
                                >
                                    {{ naira(claim.payer_amount) }}
                                </td>
                                <td v-if="claim.is_draft"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p
                        class="border-t border-border px-4 py-2 text-xs text-muted-foreground"
                    >
                        {{ payer.name }} rules: {{ payer.discount_percent }}%
                        off the facility price, {{ payer.drug_copay_percent }}%
                        enrollee co-payment on drugs.
                        <span v-if="claim.discount_amount">
                            {{ naira(claim.discount_amount) }} written off as a
                            tariff waiver on the bill.</span
                        >
                    </p>
                </section>

                <!-- Claim details -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Claim details</h2>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Enrollee number
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ claim.enrollee_number ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Plan</dt>
                            <dd class="mt-0.5 text-sm">
                                {{ claim.plan ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Service date
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ claim.service_date }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Enrolment expires
                            </dt>
                            <dd
                                class="mt-0.5 text-sm"
                                :class="{ 'text-red-600': patient.hmo_expired }"
                            >
                                {{ patient.hmo_expires_at ?? '—' }}
                                <span v-if="patient.hmo_expired"
                                    >(expired)</span
                                >
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Diagnosis
                            </dt>
                            <dd class="mt-0.5 text-sm whitespace-pre-line">
                                {{ claim.diagnosis ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Raised
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ claim.created_at }} by
                                {{ claim.created_by ?? '—' }}
                            </dd>
                        </div>
                        <div v-if="claim.submitted_at">
                            <dt class="text-xs text-muted-foreground">
                                Submitted
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ claim.submitted_at }} by
                                {{ claim.submitted_by ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="claim.status === 'rejected'"
                        class="mt-4 rounded-lg border border-red-500/30 bg-red-500/5 p-4 text-sm"
                    >
                        <p class="font-medium">Rejected by the payer</p>
                        <p class="mt-1">{{ claim.rejection_reason }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            The payer's share was already settled on the bill as
                            an HMO payment. Recover it from the enrollee or
                            write it off from Billing.
                        </p>
                    </div>
                    <p
                        v-if="claim.notes"
                        class="mt-4 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ claim.notes }}
                    </p>
                </section>
            </div>

            <aside class="flex flex-col gap-6">
                <!-- Authorisation -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-2 flex items-center justify-between">
                        <h2
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <ShieldCheck class="size-4 text-muted-foreground" />
                            Pre-authorisation
                        </h2>
                        <Button
                            v-if="claim.is_open"
                            size="sm"
                            variant="outline"
                            @click="authOpen = true"
                        >
                            {{ claim.authorization_code ? 'Edit' : 'Add' }}
                        </Button>
                    </div>
                    <template v-if="claim.authorization_code">
                        <p class="font-mono text-sm">
                            {{ claim.authorization_code }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ claim.authorized_at }}
                            <span v-if="claim.authorization_note">
                                · {{ claim.authorization_note }}</span
                            >
                        </p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        No authorisation code recorded.
                    </p>
                </section>

                <!-- Money -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <Banknote class="size-4 text-muted-foreground" />
                        Settlement
                    </h2>
                    <dl class="grid grid-cols-2 gap-y-1.5 text-sm">
                        <dt class="text-muted-foreground">Claimed</dt>
                        <dd class="text-right tabular-nums">
                            {{ naira(claim.payer_amount) }}
                        </dd>
                        <template v-if="claim.approved_amount !== null">
                            <dt class="text-muted-foreground">Approved</dt>
                            <dd class="text-right tabular-nums">
                                {{ naira(claim.approved_amount) }}
                            </dd>
                        </template>
                        <dt class="text-muted-foreground">Remitted</dt>
                        <dd class="text-right tabular-nums">
                            {{ naira(claim.paid_amount) }}
                        </dd>
                        <template v-if="claim.is_outstanding">
                            <dt class="font-medium">Outstanding</dt>
                            <dd
                                class="text-right font-semibold text-amber-600 tabular-nums"
                            >
                                {{ naira(claim.outstanding_amount) }}
                            </dd>
                        </template>
                        <template v-if="claim.shortfall_amount > 0">
                            <dt class="text-muted-foreground">Shortfall</dt>
                            <dd class="text-right text-red-600 tabular-nums">
                                {{ naira(claim.shortfall_amount) }}
                            </dd>
                        </template>
                    </dl>
                    <p
                        v-if="claim.remitted_at"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Last remittance {{ claim.remitted_at }}
                        <span v-if="claim.remittance_reference">
                            · {{ claim.remittance_reference }}</span
                        >
                    </p>
                    <div class="mt-3 grid gap-2">
                        <Button
                            v-if="claim.batch"
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link :href="claim.batch.url">
                                Schedule {{ claim.batch.batch_number }}
                            </Link>
                        </Button>
                        <Button
                            v-if="claim.bill_url"
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link :href="claim.bill_url">
                                Bill · enrollee owes
                                {{ naira(claim.bill_balance) }}
                            </Link>
                        </Button>
                    </div>
                </section>
            </aside>
        </div>

        <!-- Edit line dialog -->
        <Dialog
            :open="editing !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) editing = null;
                }
            "
        >
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Edit line</DialogTitle>
                    <DialogDescription>{{
                        editing?.description
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="saveLine">
                    <Label class="flex items-center gap-2 text-sm font-normal">
                        <Checkbox v-model="lineForm.is_covered" />
                        Covered by {{ payer.name }}
                    </Label>
                    <template v-if="lineForm.is_covered">
                        <div class="grid gap-1.5">
                            <Label for="line-amount">Tariff (₦)</Label>
                            <Input
                                id="line-amount"
                                v-model="lineForm.amount"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="lineForm.errors.amount" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="line-copay"
                                >Enrollee co-payment (₦)</Label
                            >
                            <Input
                                id="line-copay"
                                v-model="lineForm.copay_amount"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError
                                :message="lineForm.errors.copay_amount"
                            />
                        </div>
                    </template>
                    <p v-else class="text-xs text-muted-foreground">
                        The enrollee pays the full facility price of
                        {{ naira(editing?.gross_amount ?? 0) }}.
                    </p>
                    <div class="grid gap-1.5">
                        <Label for="line-remark">Remark</Label>
                        <Input
                            id="line-remark"
                            v-model="lineForm.remark"
                            placeholder="Optional"
                        />
                        <InputError :message="lineForm.errors.remark" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editing = null"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="lineForm.processing">
                            <Spinner v-if="lineForm.processing" />
                            Save line
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Authorisation dialog -->
        <Dialog v-model:open="authOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Pre-authorisation</DialogTitle>
                    <DialogDescription>
                        The code {{ payer.name }} issued for this care.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="saveAuth">
                    <div class="grid gap-1.5">
                        <Label for="auth-code">Authorisation code *</Label>
                        <Input
                            id="auth-code"
                            v-model="authForm.authorization_code"
                            class="font-mono"
                        />
                        <InputError
                            :message="authForm.errors.authorization_code"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="auth-date">Issued on</Label>
                        <Input
                            id="auth-date"
                            v-model="authForm.authorized_at"
                            type="date"
                        />
                        <InputError :message="authForm.errors.authorized_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="auth-note">Note</Label>
                        <textarea
                            id="auth-note"
                            v-model="authForm.authorization_note"
                            :class="textareaClass"
                            rows="2"
                            placeholder="Who approved, what was approved"
                        ></textarea>
                        <InputError
                            :message="authForm.errors.authorization_note"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="authOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="authForm.processing">
                            <Spinner v-if="authForm.processing" />
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Remittance dialog -->
        <Dialog v-model:open="remitOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Record remittance</DialogTitle>
                    <DialogDescription>
                        Enter what {{ payer.name }} approved and what has been
                        received. The claim settles once the approved amount is
                        paid in full.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="saveRemit">
                    <div class="grid gap-1.5">
                        <Label for="remit-approved"
                            >Approved amount (₦) *</Label
                        >
                        <Input
                            id="remit-approved"
                            v-model="remitForm.approved_amount"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <InputError
                            :message="remitForm.errors.approved_amount"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="remit-paid"
                            >Amount received now (₦) *</Label
                        >
                        <Input
                            id="remit-paid"
                            v-model="remitForm.paid_amount"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <InputError :message="remitForm.errors.paid_amount" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="remit-ref">Reference</Label>
                            <Input
                                id="remit-ref"
                                v-model="remitForm.reference"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="remit-date">Received on</Label>
                            <Input
                                id="remit-date"
                                v-model="remitForm.paid_at"
                                type="date"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="remit-note">Note</Label>
                        <Input
                            id="remit-note"
                            v-model="remitForm.note"
                            placeholder="e.g. deductions explained"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="remitOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="remitForm.processing">
                            <Spinner v-if="remitForm.processing" />
                            Record
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Reject dialog -->
        <Dialog v-model:open="rejectOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Mark as rejected</DialogTitle>
                    <DialogDescription>
                        Record the payer's reason so the amount can be followed
                        up.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="saveReject">
                    <div class="grid gap-1.5">
                        <Label for="reject-reason">Reason *</Label>
                        <textarea
                            id="reject-reason"
                            v-model="rejectForm.reason"
                            :class="textareaClass"
                            rows="3"
                        ></textarea>
                        <InputError :message="rejectForm.errors.reason" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="rejectOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="rejectForm.processing"
                        >
                            <Spinner v-if="rejectForm.processing" />
                            Mark rejected
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Discard dialog -->
        <Dialog v-model:open="discardOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Discard this draft?</DialogTitle>
                    <DialogDescription>
                        The HMO payment and any tariff waiver are removed from
                        the bill, so the enrollee owes the full amount again.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="ghost" @click="discardOpen = false"
                        >Keep</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="discarding"
                        @click="discard"
                    >
                        <Spinner v-if="discarding" />
                        Discard claim
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
