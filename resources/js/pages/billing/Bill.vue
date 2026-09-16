<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    FileText,
    HandCoins,
    Plus,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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

const props = defineProps<{
    bill: {
        id: number;
        status: string;
        status_label: string;
        tone: string;
        is_open: boolean;
        total: number;
        paid: number;
        balance: number;
        created_at: string | null;
    };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex_label: string;
        age: number | null;
        coverage: string;
        payer: string | null;
        url: string;
    };
    canClaim: boolean;
    claims: Array<{
        id: number;
        claim_number: string;
        status_label: string;
        tone: string;
        url: string | null;
    }>;
    charges: Array<{
        id: number;
        source: string;
        description: string;
        quantity: number;
        unit_price: number;
        total: number;
        claimed: boolean;
        at: string | null;
    }>;
    payments: Array<{
        id: number;
        amount: number;
        method: string;
        reference: string | null;
        received_by: string | null;
        at: string | null;
    }>;
    methods: Array<{ value: string; label: string }>;
    services: Array<{
        id: number;
        label: string;
        price: number;
        category: string;
    }>;
}>();

const payForm = useForm({ amount: '', method: 'cash', reference: '' });

function pay() {
    payForm.post(`/billing/${props.bill.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => payForm.reset(),
    });
}

// Add a charge — a fee-schedule service or a custom line.
const pick = ref('');
const chargeForm = useForm({
    description: '',
    unit_price: '',
    quantity: '1',
});
const isCustom = computed(() => pick.value === 'custom');

function addCharge() {
    chargeForm
        .transform((data) =>
            isCustom.value
                ? {
                      description: data.description,
                      unit_price: data.unit_price,
                      quantity: data.quantity,
                  }
                : {
                      service_charge_id: Number(pick.value),
                      quantity: data.quantity,
                  },
        )
        .post(`/billing/${props.bill.id}/charge`, {
            preserveScroll: true,
            onSuccess: () => {
                chargeForm.reset();
                pick.value = '';
            },
        });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Billing', href: '/billing' },
            { title: 'Bill', href: '#' },
        ],
    },
});

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        green: 'bg-green-500/10 text-green-700 dark:text-green-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

function sourceClass(source: string): string {
    const map: Record<string, string> = {
        pharmacy: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        laboratory: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        consultation: 'bg-teal-500/10 text-teal-700 dark:text-teal-400',
    };

    return map[source] ?? 'bg-muted text-muted-foreground';
}

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head :title="`Bill #${bill.id} — ${patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/billing"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to billing
        </Link>

        <div class="rounded-xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary"
                    >
                        {{ patient.initials }}
                    </span>
                    <div>
                        <h1 class="text-lg font-semibold tracking-tight">
                            {{ patient.name }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            <span class="font-mono">{{
                                patient.file_number
                            }}</span>
                            · {{ patient.sex_label
                            }}{{
                                patient.age !== null
                                    ? ' · ' + patient.age + 'y'
                                    : ''
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Bill #{{ bill.id }} · opened {{ bill.created_at }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="toneClass(bill.tone)"
                        >{{ bill.status_label }}</span
                    >
                    <template v-for="c in claims" :key="c.id">
                        <Link
                            v-if="c.url"
                            :href="c.url"
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium hover:underline"
                        >
                            <HandCoins class="size-3" />
                            {{ c.claim_number }} · {{ c.status_label }}
                        </Link>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            <HandCoins class="size-3" />
                            {{ c.claim_number }} · {{ c.status_label }}
                        </span>
                    </template>
                    <Button v-if="canClaim" as-child size="sm">
                        <Link :href="`/claims?bill_id=${bill.id}`">
                            <HandCoins class="size-4" />
                            HMO claim
                        </Link>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <a
                            :href="`/billing/${bill.id}/invoice`"
                            target="_blank"
                            rel="noopener"
                        >
                            <FileText class="size-4" />
                            Invoice
                        </a>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="patient.url">
                            <ExternalLink class="size-4" />
                            Profile
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Charges -->
        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 text-sm font-semibold">Charges</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-3 font-medium">Item</th>
                            <th class="py-2 pr-3 font-medium">Source</th>
                            <th class="py-2 pr-3 text-right font-medium">
                                Qty
                            </th>
                            <th class="py-2 pr-3 text-right font-medium">
                                Unit
                            </th>
                            <th class="py-2 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="c in charges" :key="c.id">
                            <td class="py-2 pr-3">
                                <div class="text-foreground">
                                    {{ c.description }}
                                </div>
                                <div
                                    class="text-[11px] text-muted-foreground/70"
                                >
                                    {{ c.at }}
                                </div>
                            </td>
                            <td class="py-2 pr-3">
                                <span
                                    class="rounded px-1.5 py-0.5 text-[11px] font-medium capitalize"
                                    :class="sourceClass(c.source)"
                                    >{{ c.source }}</span
                                >
                            </td>
                            <td
                                class="py-2 pr-3 text-right text-muted-foreground"
                            >
                                {{ c.quantity }}
                            </td>
                            <td
                                class="py-2 pr-3 text-right text-muted-foreground"
                            >
                                {{ money(c.unit_price) }}
                            </td>
                            <td class="py-2 text-right font-medium">
                                {{ money(c.total) }}
                            </td>
                        </tr>
                        <tr v-if="!charges.length">
                            <td
                                colspan="5"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                No charges on this bill yet.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="charges.length">
                        <tr class="border-t border-border">
                            <td
                                colspan="4"
                                class="py-3 text-right text-sm font-medium"
                            >
                                Total
                            </td>
                            <td class="py-3 text-right text-lg font-semibold">
                                {{ money(bill.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Add charge -->
        <section
            v-if="bill.is_open"
            class="rounded-xl border border-border bg-card p-5"
        >
            <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold">
                <Plus class="size-4 text-primary" />
                Add charge
            </h2>
            <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="addCharge">
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label>Service</Label>
                    <Select v-model="pick">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Choose a service…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in services"
                                :key="s.id"
                                :value="String(s.id)"
                                >{{ s.label }} —
                                {{ money(s.price) }}</SelectItem
                            >
                            <SelectItem value="custom"
                                >Custom charge…</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Quantity</Label>
                    <Input
                        v-model="chargeForm.quantity"
                        type="number"
                        min="1"
                    />
                </div>
                <div class="flex items-end">
                    <Button
                        type="submit"
                        :disabled="!pick || chargeForm.processing"
                    >
                        <Plus class="size-4" />
                        Add
                    </Button>
                </div>
                <template v-if="isCustom">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Description *</Label>
                        <Input
                            v-model="chargeForm.description"
                            placeholder="e.g. Ambulance service"
                        />
                        <InputError :message="chargeForm.errors.description" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Unit price *</Label>
                        <Input
                            v-model="chargeForm.unit_price"
                            type="number"
                            step="0.01"
                        />
                        <InputError :message="chargeForm.errors.unit_price" />
                    </div>
                </template>
            </form>
        </section>

        <!-- Settlement -->
        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold">
                <Wallet class="size-4 text-primary" />
                Payment
            </h2>
            <dl class="mb-4 grid grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-xs text-muted-foreground">Total</dt>
                    <dd class="text-base font-semibold">
                        {{ money(bill.total) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Paid</dt>
                    <dd
                        class="text-base font-semibold text-green-700 dark:text-green-400"
                    >
                        {{ money(bill.paid) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Balance</dt>
                    <dd
                        class="text-base font-semibold"
                        :class="
                            bill.balance > 0
                                ? 'text-amber-700 dark:text-amber-400'
                                : ''
                        "
                    >
                        {{ money(bill.balance) }}
                    </dd>
                </div>
            </dl>

            <form
                v-if="bill.is_open"
                class="grid gap-3 sm:grid-cols-4"
                @submit.prevent="pay"
            >
                <div class="grid gap-1.5">
                    <Label>Amount *</Label>
                    <Input
                        v-model="payForm.amount"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                    />
                    <InputError :message="payForm.errors.amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Method *</Label>
                    <Select v-model="payForm.method">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="m in methods"
                                :key="m.value"
                                :value="m.value"
                                >{{ m.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Reference</Label>
                    <Input
                        v-model="payForm.reference"
                        placeholder="Txn / receipt"
                    />
                </div>
                <div class="flex items-end">
                    <Button type="submit" :disabled="payForm.processing">
                        <Wallet class="size-4" />
                        Take payment
                    </Button>
                </div>
            </form>
            <p
                v-else
                class="text-sm font-medium text-green-700 dark:text-green-400"
            >
                This bill is settled.
            </p>

            <div v-if="payments.length" class="mt-5">
                <h3
                    class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Payments
                </h3>
                <ul class="flex flex-col divide-y divide-border/60 text-sm">
                    <li
                        v-for="p in payments"
                        :key="p.id"
                        class="flex items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                    >
                        <span>
                            <span class="font-medium">{{
                                money(p.amount)
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                · {{ p.method
                                }}<span v-if="p.reference">
                                    · {{ p.reference }}</span
                                ></span
                            >
                        </span>
                        <span class="text-xs text-muted-foreground/70"
                            >{{ p.received_by
                            }}<span v-if="p.at"> · {{ p.at }}</span></span
                        >
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
