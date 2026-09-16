<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { HandCoins, Plus, Search, ShieldCheck } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import ClaimsNav from '@/components/claims/ClaimsNav.vue';
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
import { naira } from '@/lib/money';

type ClaimRow = {
    id: number;
    claim_number: string;
    patient: string;
    file_number: string;
    payer: string;
    payer_code: string;
    service_date: string;
    gross_amount: number;
    payer_amount: number;
    paid_amount: number;
    status: string;
    status_label: string;
    tone: string;
    batch: string | null;
    has_authorization: boolean;
    url: string;
};

type ClaimableBill = {
    id: number;
    label: string;
    opened_at: string | null;
    total: number;
    unclaimed_count: number;
    unclaimed_total: number;
    patient: {
        id: number;
        name: string;
        file_number: string;
        enrollee_number: string | null;
        payer: string | null;
        payer_active: boolean;
    };
};

type SearchedPatient = {
    id: number;
    name: string;
    file_number: string;
    enrollee_number: string | null;
    payer: string | null;
    payer_active: boolean;
    bills: ClaimableBill[];
};

const props = defineProps<{
    claims: {
        data: ClaimRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { status: string; payer_id: number | null; q: string };
    stats: {
        draft_count: number;
        draft_amount: number;
        outstanding_count: number;
        outstanding_amount: number;
        paid_month: number;
        rejected_count: number;
    };
    payers: Array<{
        id: number;
        name: string;
        code: string;
        is_active: boolean;
    }>;
    statuses: Array<{ value: string; label: string }>;
    preselectedBill: ClaimableBill | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Claims', href: '/claims' }],
    },
});

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

// --- Filters ---
const status = ref(props.filters.status || 'all');
const payerId = ref(
    props.filters.payer_id ? String(props.filters.payer_id) : 'all',
);
const q = ref(props.filters.q ?? '');
let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    router.get(
        '/claims',
        {
            status: status.value === 'all' ? undefined : status.value,
            payer_id: payerId.value === 'all' ? undefined : payerId.value,
            q: q.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch([status, payerId], applyFilters);
watch(q, () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 300);
});

// --- New claim dialog ---
const newOpen = ref(false);
const patientQuery = ref('');
const results = ref<SearchedPatient[]>([]);
const searching = ref(false);
const chosen = ref<ClaimableBill | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(patientQuery, (value) => {
    clearTimeout(searchTimer);

    if (value.trim().length < 2) {
        results.value = [];

        return;
    }

    searching.value = true;
    searchTimer = setTimeout(async () => {
        const res = await fetch(
            `/claims/bill-search?q=${encodeURIComponent(value)}`,
            { headers: { Accept: 'application/json' } },
        );
        results.value = (await res.json()).patients ?? [];
        searching.value = false;
    }, 250);
});

const claimForm = useForm<{ bill_id: number | null }>({ bill_id: null });

function openNew(bill: ClaimableBill | null = null) {
    claimForm.reset();
    claimForm.clearErrors();
    chosen.value = bill;
    patientQuery.value = '';
    results.value = [];
    newOpen.value = true;
}

function chooseBill(bill: ClaimableBill) {
    chosen.value = bill;
    claimForm.bill_id = bill.id;
}

function submitClaim() {
    if (!chosen.value) {
        return;
    }

    claimForm.bill_id = chosen.value.id;
    claimForm.post('/claims', {
        onSuccess: () => {
            newOpen.value = false;
        },
    });
}

onMounted(() => {
    if (props.preselectedBill) {
        openNew(props.preselectedBill);
    }
});
</script>

<template>
    <Head title="Claims" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    HMO &amp; NHIA claims
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Raise claims from enrollees' bills, submit them in monthly
                    schedules, and record what each payer remits.
                </p>
            </div>
            <Button @click="openNew()">
                <Plus class="size-4" />
                New claim
            </Button>
        </div>

        <ClaimsNav current="claims" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Drafts to submit</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.draft_count }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ naira(stats.draft_amount, 0) }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Awaiting payers</p>
                <p class="mt-1 text-2xl font-semibold text-amber-600">
                    {{ naira(stats.outstanding_amount, 0) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ stats.outstanding_count }} claims
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Remitted this month</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">
                    {{ naira(stats.paid_month, 0) }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Rejected (90 days)</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="stats.rejected_count ? 'text-red-600' : ''"
                >
                    {{ stats.rejected_count }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="w-64 pl-8"
                    placeholder="Claim, enrollee or patient"
                />
            </div>
            <Select v-model="status">
                <SelectTrigger class="w-44">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem value="outstanding">Outstanding</SelectItem>
                    <SelectItem
                        v-for="s in statuses"
                        :key="s.value"
                        :value="s.value"
                        >{{ s.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="payerId">
                <SelectTrigger class="w-56">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All payers</SelectItem>
                    <SelectItem
                        v-for="p in payers"
                        :key="p.id"
                        :value="String(p.id)"
                        >{{ p.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <div
            v-if="!claims.data.length"
            class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
        >
            No claims match. Raise one from an enrollee's bill to get started.
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
                        <th class="px-4 py-2.5 font-medium">Claim</th>
                        <th class="px-4 py-2.5 font-medium">Patient</th>
                        <th class="px-4 py-2.5 font-medium">Payer</th>
                        <th class="px-4 py-2.5 font-medium">Service date</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Claimed
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">Paid</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="c in claims.data" :key="c.id">
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <p class="font-mono text-xs">
                                {{ c.claim_number }}
                            </p>
                            <p
                                v-if="c.batch"
                                class="text-xs text-muted-foreground"
                            >
                                {{ c.batch }}
                            </p>
                        </td>
                        <td class="px-4 py-2.5">
                            <p class="font-medium">{{ c.patient }}</p>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ c.file_number }}
                            </p>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            {{ c.payer }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            {{ c.service_date }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ naira(c.payer_amount) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ naira(c.paid_amount) }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="toneClass(c.tone)"
                            >
                                {{ c.status_label }}
                            </span>
                            <ShieldCheck
                                v-if="c.has_authorization"
                                class="ml-1 inline size-3.5 text-emerald-600"
                                aria-label="Authorised"
                            />
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <Button as-child size="sm" variant="outline">
                                <Link :href="c.url">Open</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="claims.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span
                >Page {{ claims.current_page }} of {{ claims.last_page }} ·
                {{ claims.total }} claims</span
            >
            <div class="flex gap-2">
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    :disabled="!claims.prev_page_url"
                >
                    <Link :href="claims.prev_page_url ?? '#'" preserve-scroll
                        >Previous</Link
                    >
                </Button>
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    :disabled="!claims.next_page_url"
                >
                    <Link :href="claims.next_page_url ?? '#'" preserve-scroll
                        >Next</Link
                    >
                </Button>
            </div>
        </div>

        <!-- New claim dialog -->
        <Dialog v-model:open="newOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Raise a claim</DialogTitle>
                    <DialogDescription>
                        Find an enrollee and pick the bill to claim. Every
                        charge not yet claimed goes on the claim; you can adjust
                        lines before submitting.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="chosen" class="grid gap-3">
                    <div class="rounded-lg border border-border p-3 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">
                                    {{ chosen.patient.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-mono">{{
                                        chosen.patient.file_number
                                    }}</span>
                                    · {{ chosen.patient.payer ?? 'No payer' }}
                                    <span v-if="chosen.patient.enrollee_number">
                                        ·
                                        {{
                                            chosen.patient.enrollee_number
                                        }}</span
                                    >
                                </p>
                            </div>
                            <button
                                type="button"
                                class="text-xs text-muted-foreground hover:underline"
                                @click="chosen = null"
                            >
                                Change
                            </button>
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <dt class="text-muted-foreground">Bill</dt>
                                <dd class="font-medium">
                                    {{ chosen.label }}
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >· {{ chosen.opened_at }}</span
                                    >
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Unclaimed charges
                                </dt>
                                <dd class="font-medium">
                                    {{ chosen.unclaimed_count }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">To claim</dt>
                                <dd class="font-medium tabular-nums">
                                    {{ naira(chosen.unclaimed_total) }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="!chosen.patient.payer_active"
                            class="mt-2 text-xs text-red-600"
                        >
                            This patient has no active payer on record. Update
                            their coverage or activate the payer first.
                        </p>
                    </div>
                    <InputError :message="claimForm.errors.bill_id" />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="newOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="
                                claimForm.processing ||
                                !chosen.unclaimed_count ||
                                !chosen.patient.payer_active
                            "
                            @click="submitClaim"
                        >
                            <Spinner v-if="claimForm.processing" />
                            <HandCoins v-else class="size-4" />
                            Raise claim
                        </Button>
                    </div>
                </div>

                <div v-else class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label for="claim-patient">Enrollee</Label>
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                            />
                            <Input
                                id="claim-patient"
                                v-model="patientQuery"
                                class="pl-8"
                                placeholder="Name, file number or enrollee number"
                                autocomplete="off"
                            />
                        </div>
                    </div>
                    <p v-if="searching" class="text-xs text-muted-foreground">
                        Searching…
                    </p>
                    <p
                        v-else-if="
                            patientQuery.trim().length >= 2 && !results.length
                        "
                        class="text-xs text-muted-foreground"
                    >
                        No HMO patients with unclaimed bills match.
                    </p>
                    <div
                        v-for="p in results"
                        :key="p.id"
                        class="rounded-lg border border-border p-3 text-sm"
                    >
                        <p class="font-medium">{{ p.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            <span class="font-mono">{{ p.file_number }}</span>
                            · {{ p.payer ?? 'No payer' }}
                            <span v-if="p.enrollee_number">
                                · {{ p.enrollee_number }}</span
                            >
                        </p>
                        <p
                            v-if="!p.bills.length"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            No bills with unclaimed charges.
                        </p>
                        <ul v-else class="mt-2 divide-y divide-border">
                            <li
                                v-for="b in p.bills"
                                :key="b.id"
                                class="flex items-center justify-between gap-2 py-1.5"
                            >
                                <span class="text-xs">
                                    {{ b.label }} · {{ b.opened_at }} ·
                                    {{ b.unclaimed_count }} unclaimed ·
                                    <span class="tabular-nums">{{
                                        naira(b.unclaimed_total)
                                    }}</span>
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="chooseBill(b)"
                                    >Select</Button
                                >
                            </li>
                        </ul>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
