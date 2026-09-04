<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    Pill,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CatalogItem = {
    id: number;
    label: string;
    name: string;
    unit: string;
    selling_price: number;
    quantity_on_hand: number;
    is_low: boolean;
};

const props = defineProps<{
    entry: { id: number; service_point: string };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex_label: string;
        age: number | null;
        url: string;
    };
    prescriptions: Array<{
        id: number;
        label: string;
        name: string;
        dose: string | null;
        frequency: string | null;
        route: string | null;
    }>;
    catalog: CatalogItem[];
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
    item: CatalogItem;
    quantity: number;
    medication_id: number | null;
};

const lines = ref<Line[]>([]);
const search = ref('');
const form = useForm<{ note: string }>({ note: '' });

const filteredCatalog = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.catalog.filter((c) => !q || c.label.toLowerCase().includes(q));
});

const total = computed(() =>
    lines.value.reduce((sum, l) => sum + l.item.selling_price * l.quantity, 0),
);

// The lines payload is built via transform(), so its errors aren't on the typed form.
const linesError = computed(
    () => (form.errors as Record<string, string | undefined>).lines,
);

function addItem(item: CatalogItem, medicationId: number | null = null) {
    const existing = lines.value.find(
        (l) => l.item.id === item.id && l.medication_id === medicationId,
    );

    if (existing) {
        existing.quantity += 1;
    } else {
        lines.value.push({ item, quantity: 1, medication_id: medicationId });
    }
}

function addFromPrescription(p: (typeof props.prescriptions)[number]) {
    const name = p.name.toLowerCase();
    const match = props.catalog.find(
        (c) =>
            c.name.toLowerCase().includes(name) ||
            name.includes(c.name.toLowerCase()),
    );

    if (match) {
        addItem(match, p.id);
    }
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

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
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
            <Button as-child variant="outline" size="sm">
                <Link :href="patient.url">
                    <ExternalLink class="size-4" />
                    Full profile
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div class="flex flex-col gap-4">
                <!-- Prescriptions -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Prescribed</h2>
                    <ul
                        v-if="prescriptions.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="p in prescriptions"
                            :key="p.id"
                            class="flex items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span class="text-sm text-foreground">{{
                                p.label
                            }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:text-foreground"
                                @click="addFromPrescription(p)"
                            >
                                <Plus class="size-4" />
                                Add
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No active prescriptions on file.
                    </p>
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
                                    {{ l.item.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ money(l.item.selling_price) }} each
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
