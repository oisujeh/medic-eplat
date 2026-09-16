<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ReceiptText, Search, Tags } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    bills: Array<{
        id: number;
        status: string;
        status_label: string;
        tone: string;
        total: number;
        charges_count: number;
        created_at: string | null;
        patient: { name: string; initials: string; file_number: string };
        url: string;
    }>;
    filters: { q: string; status: string };
    counts: { open: number; paid: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Billing', href: '/billing' }] },
});

const search = ref(props.filters.q ?? '');

const tabs = [
    { key: 'open', label: 'Open', count: () => props.counts.open },
    { key: 'paid', label: 'Paid', count: () => props.counts.paid },
    { key: 'all', label: 'All', count: () => null },
];

function navigate(patch: Record<string, string>) {
    router.get(
        '/billing',
        { ...props.filters, ...patch },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        green: 'bg-green-500/10 text-green-700 dark:text-green-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="Billing" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Billing / Cashier
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Patient bills across the facility.
                </p>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link href="/billing/services">
                    <Tags class="size-4" />
                    Fee schedule
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        filters.status === tab.key
                            ? 'border-primary bg-primary/5 text-foreground'
                            : 'border-border text-muted-foreground hover:bg-muted'
                    "
                    @click="navigate({ status: tab.key })"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count() !== null"
                        class="rounded-full bg-muted px-1.5 text-[11px] text-muted-foreground"
                        >{{ tab.count() }}</span
                    >
                </button>
            </div>
            <div class="relative sm:max-w-xs">
                <Search
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Patient name or file no…"
                    class="pl-8"
                    @keyup.enter="navigate({ q: search })"
                />
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div
                v-if="!bills.length"
                class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
            >
                No bills match this view.
            </div>

            <Link
                v-for="bill in bills"
                :key="bill.id"
                :href="bill.url"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-muted/30"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                    >
                        {{ bill.patient.initials }}
                    </span>
                    <div>
                        <p class="font-medium">{{ bill.patient.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            <span class="font-mono">{{
                                bill.patient.file_number
                            }}</span>
                            · {{ bill.charges_count }} charge(s) ·
                            {{ bill.created_at }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="toneClass(bill.tone)"
                        >{{ bill.status_label }}</span
                    >
                    <span class="text-base font-semibold">{{
                        money(bill.total)
                    }}</span>
                </div>
            </Link>
        </div>

        <p
            v-if="bills.length"
            class="flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            <ReceiptText class="size-3.5" />
            Showing up to 60 bills.
        </p>
    </div>
</template>
