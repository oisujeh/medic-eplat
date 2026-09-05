<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    Pill,
    Plus,
    Search,
    Trash2,
    Undo2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { StockProduct } from '@/types/clinical';

type Prescription = {
    id: number;
    label: string;
    name: string;
    dose: string | null;
    frequency: string | null;
    route: string | null;
    quantity: number | null;
    duration_days: number | null;
    quantity_dispensed: number;
    remaining: number | null;
    outstanding: boolean;
    fulfilment: { value: string; label: string; tone: string };
    prescribed_at: string | null;
    products: StockProduct[];
};

const props = defineProps<{
    entry: { id: number; service_point: string };
    location: { id: number; name: string };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex_label: string;
        age: number | null;
        url: string;
    };
    prescriptions: Prescription[];
    catalog: StockProduct[];
    dispensed: Array<{
        id: number;
        at: string | null;
        total: number;
        items: Array<{ name: string; quantity: number; total: number }>;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pharmacy', href: '/pharmacy' },
            { title: 'Dispense', href: '#' },
        ],
    },
});

type Line = {
    item: StockProduct;
    quantity: number;
    medication_id: number | null;
    prescription: string | null;
};

const lines = ref<Line[]>([]);
const search = ref('');
const form = useForm<{ note: string }>({ note: '' });

const filteredCatalog = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.catalog.filter((c) => !q || c.label.toLowerCase().includes(q));
});

const outstanding = computed(() =>
    props.prescriptions.filter((p) => p.outstanding),
);
const settled = computed(() =>
    props.prescriptions.filter((p) => !p.outstanding),
);

const total = computed(() =>
    lines.value.reduce((sum, l) => sum + l.item.selling_price * l.quantity, 0),
);

// The lines payload is built via transform(), so its errors aren't on the typed form.
const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const linesError = computed(() => {
    const errors = formErrors.value;
    const key = Object.keys(errors).find((k) => k.startsWith('lines'));

    return key ? errors[key] : undefined;
});

function addItem(
    item: StockProduct,
    medicationId: number | null = null,
    quantity = 1,
    prescription: string | null = null,
) {
    const existing = lines.value.find(
        (l) => l.item.id === item.id && l.medication_id === medicationId,
    );

    if (existing) {
        existing.quantity += quantity;
    } else {
        lines.value.push({
            item,
            quantity,
            medication_id: medicationId,
            prescription,
        });
    }
}

function addFromPrescription(p: Prescription, product: StockProduct) {
    const already = lines.value
        .filter((l) => l.medication_id === p.id)
        .reduce((sum, l) => sum + l.quantity, 0);
    const wanted =
        p.remaining !== null ? Math.max(1, p.remaining - already) : 1;

    addItem(product, p.id, wanted, p.label);
}

function removeLine(i: number) {
    lines.value.splice(i, 1);
}

function dispense() {
    form.transform(() => ({
        note: form.note || null,
        lines: lines.value.map((l) => ({
            inventory_item_id: l.item.id,
            quantity: l.quantity,
            medication_id: l.medication_id,
        })),
    })).post(`/pharmacy/${props.entry.id}/dispense`, {
        preserveScroll: true,
        onSuccess: () => {
            lines.value = [];
            form.reset();
        },
    });
}

function markExternal(p: Prescription) {
    router.post(
        `/pharmacy/${props.entry.id}/prescriptions/${p.id}/external`,
        {},
        { preserveScroll: true },
    );
}

function reopen(p: Prescription) {
    router.post(
        `/pharmacy/${props.entry.id}/prescriptions/${p.id}/reopen`,
        {},
        { preserveScroll: true },
    );
}

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}

function toneClass(tone: string): string {
    return (
        {
            amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
            green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        }[tone] ?? 'bg-muted text-muted-foreground'
    );
}
</script>

<template>
    <Head :title="`Dispense — ${patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/pharmacy"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to pharmacy
        </Link>

        <!-- Patient header -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-card p-5"
        >
            <div class="flex items-center gap-4">
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
                        <span class="font-mono">{{ patient.file_number }}</span>
                        · {{ patient.sex_label
                        }}{{
                            patient.age !== null
                                ? ' · ' + patient.age + 'y'
                                : ''
                        }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-muted-foreground"
                    >Dispensing from
                    <span class="font-medium text-foreground">{{
                        location.name
                    }}</span></span
                >
                <Button as-child variant="outline" size="sm">
                    <Link :href="patient.url">
                        <ExternalLink class="size-4" />
                        Full profile
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div class="flex flex-col gap-4">
                <!-- Prescriptions to fill -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">
                        To dispense
                        <span
                            v-if="outstanding.length"
                            class="ml-1 text-xs font-normal text-muted-foreground"
                            >({{ outstanding.length }})</span
                        >
                    </h2>
                    <ul
                        v-if="outstanding.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="p in outstanding"
                            :key="p.id"
                            class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ p.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        <span v-if="p.quantity !== null"
                                            >{{ p.remaining }} of
                                            {{ p.quantity }} remaining</span
                                        >
                                        <span v-else>No quantity given</span>
                                        <span v-if="p.duration_days">
                                            · {{ p.duration_days }} days</span
                                        >
                                        <span v-if="p.prescribed_at">
                                            · {{ p.prescribed_at }}</span
                                        >
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="toneClass(p.fulfilment.tone)"
                                        >{{ p.fulfilment.label }}</span
                                    >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-muted-foreground hover:text-foreground"
                                        title="Patient will obtain this outside"
                                        @click="markExternal(p)"
                                    >
                                        Sourced outside
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-if="p.products.length"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Button
                                    v-for="product in p.products"
                                    :key="product.id"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="product.quantity_on_hand <= 0"
                                    @click="addFromPrescription(p, product)"
                                >
                                    <Plus class="size-3.5" />
                                    {{ product.label }}
                                    <span class="text-xs text-muted-foreground">
                                        · {{ product.quantity_on_hand }}
                                        {{ product.unit }}
                                    </span>
                                </Button>
                            </div>
                            <p
                                v-else
                                class="text-xs text-amber-700 dark:text-amber-400"
                            >
                                No stocked product matches this drug. Add a
                                substitute from stock below, or mark it as
                                sourced outside.
                            </p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        Nothing outstanding on file.
                    </p>

                    <div
                        v-if="settled.length"
                        class="mt-4 border-t border-border pt-3"
                    >
                        <h3
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Already handled
                        </h3>
                        <ul class="flex flex-col gap-1.5">
                            <li
                                v-for="p in settled"
                                :key="p.id"
                                class="flex items-center justify-between gap-2 text-sm"
                            >
                                <span class="text-muted-foreground">{{
                                    p.label
                                }}</span>
                                <span class="flex items-center gap-1">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="toneClass(p.fulfilment.tone)"
                                        >{{ p.fulfilment.label }}</span
                                    >
                                    <Button
                                        v-if="p.fulfilment.value === 'external'"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-muted-foreground hover:text-foreground"
                                        aria-label="Put back on the list"
                                        @click="reopen(p)"
                                    >
                                        <Undo2 class="size-3.5" />
                                    </Button>
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Catalog picker -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Add from stock</h2>
                    <div class="relative mb-2">
                        <Search
                            class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search stock…"
                            class="pl-8"
                        />
                    </div>
                    <div
                        class="max-h-56 divide-y divide-border/60 overflow-y-auto rounded-md border border-border"
                    >
                        <button
                            v-for="c in filteredCatalog"
                            :key="c.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-muted/40"
                            :disabled="c.quantity_on_hand <= 0"
                            :class="c.quantity_on_hand <= 0 ? 'opacity-50' : ''"
                            @click="addItem(c)"
                        >
                            <span class="text-sm text-foreground">{{
                                c.label
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ money(c.selling_price) }} ·
                                {{ c.quantity_on_hand }} {{ c.unit }}
                            </span>
                        </button>
                        <p
                            v-if="!filteredCatalog.length"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            No stock items match.
                        </p>
                    </div>
                </section>
            </div>

            <!-- Dispense cart -->
            <aside class="flex flex-col gap-3">
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">To dispense</h2>
                    <ul
                        v-if="lines.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="(l, i) in lines"
                            :key="i"
                            class="flex items-center gap-2 py-2 first:pt-0"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ l.item.label }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ money(l.item.selling_price) }} each<span
                                        v-if="l.prescription"
                                    >
                                        · for {{ l.prescription }}</span
                                    >
                                </p>
                            </div>
                            <Input
                                v-model.number="l.quantity"
                                type="number"
                                min="1"
                                class="h-8 w-16"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                                @click="removeLine(i)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        Add items from the prescription or stock.
                    </p>

                    <div
                        class="mt-3 flex items-center justify-between border-t border-border pt-3 text-sm"
                    >
                        <span class="text-muted-foreground">Total</span>
                        <span class="text-base font-semibold">{{
                            money(total)
                        }}</span>
                    </div>

                    <div class="mt-3 grid gap-1.5">
                        <Label>Note (optional)</Label>
                        <Input
                            v-model="form.note"
                            placeholder="e.g. Counselled on dosing"
                        />
                    </div>

                    <Button
                        type="button"
                        class="mt-3 w-full"
                        :disabled="!lines.length || form.processing"
                        @click="dispense"
                    >
                        <Pill class="size-4" />
                        Dispense &amp; bill
                    </Button>
                    <p
                        v-if="linesError"
                        class="mt-2 text-xs text-red-600 dark:text-red-400"
                    >
                        {{ linesError }}
                    </p>
                </section>

                <section
                    v-if="dispensed.length"
                    class="rounded-xl border border-border bg-card p-5"
                >
                    <h2 class="mb-3 text-sm font-semibold">
                        Dispensed this visit
                    </h2>
                    <ul class="flex flex-col divide-y divide-border text-sm">
                        <li
                            v-for="d in dispensed"
                            :key="d.id"
                            class="py-2 first:pt-0 last:pb-0"
                        >
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span>{{ d.at }}</span>
                                <span class="font-medium text-foreground">{{
                                    money(d.total)
                                }}</span>
                            </div>
                            <p
                                v-for="(it, j) in d.items"
                                :key="j"
                                class="text-xs text-muted-foreground"
                            >
                                {{ it.quantity }} × {{ it.name }}
                            </p>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>
