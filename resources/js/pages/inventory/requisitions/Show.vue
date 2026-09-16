<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, PackageCheck, Send, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Line = {
    id: number;
    item: string;
    unit: string;
    quantity_requested: number;
    quantity_approved: number | null;
    quantity_issued: number;
    quantity_received: number;
    available_at_source: number;
    note: string | null;
};

const props = defineProps<{
    requisition: {
        id: number;
        number: string;
        status: { value: string; label: string; tone: string };
        from: string | null;
        to: string | null;
        note: string | null;
        cancel_reason: string | null;
        requested_by: string | null;
        requested_at: string | null;
        approved_by: string | null;
        approved_at: string | null;
        issued_by: string | null;
        issued_at: string | null;
        received_by: string | null;
        received_at: string | null;
    };
    lines: Line[];
    can: {
        approve: boolean;
        issue: boolean;
        receive: boolean;
        cancel: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: '/inventory' },
            { title: 'Requisitions', href: '/inventory/requisitions' },
            { title: 'Requisition', href: '#' },
        ],
    },
});

const base = computed(() => `/inventory/requisitions/${props.requisition.id}`);

// Quantities being approved (store) or confirmed received (destination).
const approveQty = ref<Record<number, number>>(
    Object.fromEntries(
        props.lines.map((l) => [
            l.id,
            Math.min(l.quantity_requested, l.available_at_source),
        ]),
    ),
);
const receiveQty = ref<Record<number, number>>(
    Object.fromEntries(props.lines.map((l) => [l.id, l.quantity_issued])),
);

const approveForm = useForm({});
const issueForm = useForm({});
const receiveForm = useForm({});
const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });

const actionError = computed(() => {
    for (const f of [approveForm, issueForm, receiveForm]) {
        const e = (f.errors as Record<string, string | undefined>).requisition;

        if (e) {
            return e;
        }
    }

    return undefined;
});

function approve() {
    approveForm
        .transform(() => ({ quantities: approveQty.value }))
        .post(`${base.value}/approve`, { preserveScroll: true });
}

function issue() {
    issueForm.post(`${base.value}/issue`, { preserveScroll: true });
}

function receive() {
    receiveForm
        .transform(() => ({ quantities: receiveQty.value }))
        .post(`${base.value}/receive`, { preserveScroll: true });
}

function cancel() {
    cancelForm.post(`${base.value}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
        },
    });
}

function toneClass(tone: string): string {
    return (
        {
            amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
            violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
            green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        }[tone] ?? 'bg-muted text-muted-foreground'
    );
}

const showApprovedColumn = computed(() =>
    ['approved', 'issued', 'received'].includes(props.requisition.status.value),
);
const showIssuedColumn = computed(() =>
    ['issued', 'received'].includes(props.requisition.status.value),
);
const showReceivedColumn = computed(
    () => props.requisition.status.value === 'received',
);
</script>

<template>
    <Head :title="requisition.number" />

    <div class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/inventory/requisitions"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            All requisitions
        </Link>

        <div class="rounded-xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="font-mono text-lg font-semibold tracking-tight">
                        {{ requisition.number }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ requisition.from }}
                        <span class="text-muted-foreground/60">→</span>
                        {{ requisition.to }}
                    </p>
                </div>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="toneClass(requisition.status.tone)"
                    >{{ requisition.status.label }}</span
                >
            </div>
            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Raised</dt>
                    <dd>
                        {{ requisition.requested_at }}
                        <span
                            v-if="requisition.requested_by"
                            class="text-muted-foreground"
                            >· {{ requisition.requested_by }}</span
                        >
                    </dd>
                </div>
                <div v-if="requisition.approved_at">
                    <dt class="text-xs text-muted-foreground">Approved</dt>
                    <dd>
                        {{ requisition.approved_at }}
                        <span class="text-muted-foreground"
                            >· {{ requisition.approved_by }}</span
                        >
                    </dd>
                </div>
                <div v-if="requisition.issued_at">
                    <dt class="text-xs text-muted-foreground">Issued</dt>
                    <dd>
                        {{ requisition.issued_at }}
                        <span class="text-muted-foreground"
                            >· {{ requisition.issued_by }}</span
                        >
                    </dd>
                </div>
                <div v-if="requisition.received_at">
                    <dt class="text-xs text-muted-foreground">Received</dt>
                    <dd>
                        {{ requisition.received_at }}
                        <span class="text-muted-foreground"
                            >· {{ requisition.received_by }}</span
                        >
                    </dd>
                </div>
            </dl>
            <p
                v-if="requisition.note"
                class="mt-3 text-sm text-muted-foreground"
            >
                {{ requisition.note }}
            </p>
            <p
                v-if="requisition.cancel_reason"
                class="mt-3 text-sm text-red-600 dark:text-red-400"
            >
                Cancelled: {{ requisition.cancel_reason }}
            </p>
        </div>

        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 text-sm font-semibold">Lines</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-3 font-medium">Item</th>
                            <th class="py-2 pr-3 text-right font-medium">
                                Requested
                            </th>
                            <th
                                v-if="can.approve"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                At {{ requisition.from }}
                            </th>
                            <th
                                v-if="can.approve || showApprovedColumn"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                Approved
                            </th>
                            <th
                                v-if="showIssuedColumn"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                Issued
                            </th>
                            <th
                                v-if="can.receive || showReceivedColumn"
                                class="py-2 pr-3 text-right font-medium"
                            >
                                Received
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="l in lines" :key="l.id">
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ l.item }}</div>
                                <div
                                    v-if="l.note"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ l.note }}
                                </div>
                            </td>
                            <td class="py-2 pr-3 text-right">
                                {{ l.quantity_requested }} {{ l.unit }}
                            </td>
                            <td
                                v-if="can.approve"
                                class="py-2 pr-3 text-right"
                                :class="
                                    l.available_at_source < l.quantity_requested
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ l.available_at_source }}
                            </td>
                            <td v-if="can.approve" class="py-2 pr-3 text-right">
                                <Input
                                    v-model.number="approveQty[l.id]"
                                    type="number"
                                    min="0"
                                    :max="l.available_at_source"
                                    class="ml-auto h-8 w-24 text-right"
                                />
                            </td>
                            <td
                                v-else-if="showApprovedColumn"
                                class="py-2 pr-3 text-right"
                            >
                                {{ l.quantity_approved ?? '—' }}
                            </td>
                            <td
                                v-if="showIssuedColumn"
                                class="py-2 pr-3 text-right"
                            >
                                {{ l.quantity_issued }}
                            </td>
                            <td v-if="can.receive" class="py-2 pr-3 text-right">
                                <Input
                                    v-model.number="receiveQty[l.id]"
                                    type="number"
                                    min="0"
                                    :max="l.quantity_issued"
                                    class="ml-auto h-8 w-24 text-right"
                                />
                            </td>
                            <td
                                v-else-if="showReceivedColumn"
                                class="py-2 pr-3 text-right"
                                :class="
                                    l.quantity_received < l.quantity_issued
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : ''
                                "
                            >
                                {{ l.quantity_received }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p
                v-if="actionError"
                class="mt-3 text-sm text-red-600 dark:text-red-400"
            >
                {{ actionError }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <Button
                    v-if="can.approve"
                    type="button"
                    :disabled="approveForm.processing"
                    @click="approve"
                >
                    <Check class="size-4" />
                    Approve quantities
                </Button>
                <Button
                    v-if="can.issue"
                    type="button"
                    :disabled="issueForm.processing"
                    @click="issue"
                >
                    <Send class="size-4" />
                    Issue stock
                </Button>
                <Button
                    v-if="can.receive"
                    type="button"
                    :disabled="receiveForm.processing"
                    @click="receive"
                >
                    <PackageCheck class="size-4" />
                    Confirm receipt
                </Button>
                <Button
                    v-if="can.cancel"
                    type="button"
                    variant="ghost"
                    class="text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                    @click="cancelOpen = true"
                >
                    <X class="size-4" />
                    Cancel requisition
                </Button>
            </div>
        </section>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel {{ requisition.number }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-3" @submit.prevent="cancel">
                    <div class="grid gap-1.5">
                        <Label>Reason</Label>
                        <Input
                            v-model="cancelForm.reason"
                            placeholder="e.g. Raised in error"
                        />
                        <InputError :message="cancelForm.errors.reason" />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="cancelForm.processing"
                        >
                            Cancel requisition
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
