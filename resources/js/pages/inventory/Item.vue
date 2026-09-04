<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackagePlus, SlidersHorizontal } from '@lucide/vue';
import { ref } from 'vue';
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

type Batch = {
    id: number;
    batch_number: string | null;
    expiry_date: string | null;
    quantity: number;
    is_expired: boolean;
};

const props = defineProps<{
    item: {
        id: number;
        code: string;
        name: string;
        label: string;
        category: string;
        unit: string;
        selling_price: number;
        cost_price: number | null;
        quantity_on_hand: number;
        reorder_level: number;
        is_low: boolean;
    };
    batches: Batch[];
    movements: Array<{
        id: number;
        type: string;
        quantity_change: number;
        reason: string | null;
        at: string | null;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: '/inventory' },
            { title: 'Item', href: '#' },
        ],
    },
});

const receiveForm = useForm({
    quantity: '',
    batch_number: '',
    expiry_date: '',
    cost_price: '',
});

const adjustBatch = ref<Batch | null>(null);
const adjustForm = useForm({ delta: '', reason: '' });

function receive() {
    receiveForm.post(`/inventory/${props.item.id}/receive`, {
        preserveScroll: true,
        onSuccess: () => receiveForm.reset(),
    });
}

function openAdjust(batch: Batch) {
    adjustBatch.value = batch;
    adjustForm.reset();
    adjustForm.clearErrors();
}

function submitAdjust() {
    if (!adjustBatch.value) {
        return;
    }

    adjustForm.post(`/inventory/batches/${adjustBatch.value.id}/adjust`, {
        preserveScroll: true,
        onSuccess: () => {
            adjustBatch.value = null;
        },
    });
}

function money(v: number | null): string {
    if (v === null) {
        return '—';
    }

    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head :title="item.label" />

    <div class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/inventory"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to store
        </Link>

        <div class="rounded-xl border border-border bg-card p-5">
            <h1 class="text-lg font-semibold tracking-tight">
                {{ item.label }}
            </h1>
            <p class="text-sm text-muted-foreground">
                <span class="font-mono">{{ item.code }}</span> ·
                {{ item.category }}
            </p>
            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-muted-foreground">On hand</dt>
                    <dd
                        class="text-base font-semibold"
                        :class="
                            item.is_low
                                ? 'text-amber-700 dark:text-amber-400'
                                : ''
                        "
                    >
                        {{ item.quantity_on_hand }} {{ item.unit }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Reorder level</dt>
                    <dd class="text-base font-semibold">
                        {{ item.reorder_level }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Selling price</dt>
                    <dd class="text-base font-semibold">
                        {{ money(item.selling_price) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Cost price</dt>
                    <dd class="text-base font-semibold">
                        {{ money(item.cost_price) }}
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Receive stock -->
        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-4 flex items-center gap-1.5 text-sm font-semibold">
                <PackagePlus class="size-4 text-primary" />
                Receive stock
            </h2>
            <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="receive">
                <div class="grid gap-1.5">
                    <Label>Quantity *</Label>
                    <Input
                        v-model="receiveForm.quantity"
                        type="number"
                        placeholder="0"
                    />
                    <InputError :message="receiveForm.errors.quantity" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Batch no.</Label>
                    <Input v-model="receiveForm.batch_number" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Expiry</Label>
                    <Input v-model="receiveForm.expiry_date" type="date" />
                    <InputError :message="receiveForm.errors.expiry_date" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Cost price</Label>
                    <Input
                        v-model="receiveForm.cost_price"
                        type="number"
                        step="0.01"
                    />
                </div>
                <div class="sm:col-span-4">
                    <Button type="submit" :disabled="receiveForm.processing">
                        <PackagePlus class="size-4" />
                        Receive
                    </Button>
                </div>
            </form>
        </section>

        <!-- Batches -->
        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 text-sm font-semibold">Batches</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-3 font-medium">Batch</th>
                            <th class="py-2 pr-3 font-medium">Expiry</th>
                            <th class="py-2 pr-3 font-medium">Qty</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="b in batches" :key="b.id">
                            <td class="py-2 pr-3 font-mono text-xs">
                                {{ b.batch_number ?? '—' }}
                            </td>
                            <td class="py-2 pr-3">
                                <span
                                    :class="
                                        b.is_expired
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-muted-foreground'
                                    "
                                    >{{ b.expiry_date ?? '—'
                                    }}{{
                                        b.is_expired ? ' (expired)' : ''
                                    }}</span
                                >
                            </td>
                            <td class="py-2 pr-3 font-medium">
                                {{ b.quantity }}
                            </td>
                            <td class="py-2 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="openAdjust(b)"
                                >
                                    <SlidersHorizontal class="size-4" />
                                    Adjust
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!batches.length">
                            <td
                                colspan="4"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                No batches yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Movements -->
        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-3 text-sm font-semibold">Recent movements</h2>
            <ul
                v-if="movements.length"
                class="flex flex-col divide-y divide-border/60 text-sm"
            >
                <li
                    v-for="m in movements"
                    :key="m.id"
                    class="flex items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                >
                    <span>
                        <span class="font-medium">{{ m.type }}</span>
                        <span
                            v-if="m.reason"
                            class="text-xs text-muted-foreground"
                        >
                            · {{ m.reason }}</span
                        >
                    </span>
                    <span class="flex items-center gap-3">
                        <span
                            class="font-medium"
                            :class="
                                m.quantity_change < 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-green-700 dark:text-green-400'
                            "
                            >{{ m.quantity_change > 0 ? '+' : ''
                            }}{{ m.quantity_change }}</span
                        >
                        <span class="text-xs text-muted-foreground/70">{{
                            m.at
                        }}</span>
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No movements yet.
            </p>
        </section>

        <!-- Adjust dialog -->
        <Dialog
            :open="adjustBatch !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) adjustBatch = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle
                        >Adjust batch
                        {{ adjustBatch?.batch_number ?? '' }}</DialogTitle
                    >
                </DialogHeader>
                <form
                    class="flex flex-col gap-3"
                    @submit.prevent="submitAdjust"
                >
                    <div class="grid gap-1.5">
                        <Label>Change (+ / −) *</Label>
                        <Input
                            v-model="adjustForm.delta"
                            type="number"
                            placeholder="e.g. -3"
                        />
                        <InputError :message="adjustForm.errors.delta" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Reason</Label>
                        <Input
                            v-model="adjustForm.reason"
                            placeholder="e.g. Damaged, stock count"
                        />
                    </div>
                    <div>
                        <Button type="submit" :disabled="adjustForm.processing">
                            Save adjustment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
