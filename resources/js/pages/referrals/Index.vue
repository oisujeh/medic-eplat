<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Send, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
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
import { index as referralsIndex } from '@/routes/referrals';
import type { Referral } from '@/types/clinical';

type Row = Referral & {
    patient: {
        id: number;
        name: string;
        file_number: string;
        sex: string;
        age: number | null;
    };
    referred_by: string | null;
    days_open: number | null;
};

type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Option = { value: string; label: string };

const props = defineProps<{
    referrals: Paginated<Row>;
    filters: {
        status: string;
        urgency: string;
        search: string;
        from: string;
        to: string;
    };
    summary: { open: number; awaiting_feedback: number; this_month: number };
    statuses: Option[];
    urgencies: Option[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Referrals', href: '/referrals' }] },
});

const ALL = 'all';

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status || ALL);
const urgency = ref(props.filters.urgency || ALL);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function apply() {
    router.get(
        referralsIndex().url,
        {
            search: search.value || undefined,
            status: status.value === ALL ? undefined : status.value,
            urgency: urgency.value === ALL ? undefined : urgency.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watchDebounced(search, apply, { debounce: 300 });
watch([status, urgency, from, to], apply);

const hasFilters = computed(
    () =>
        search.value !== '' ||
        status.value !== ALL ||
        urgency.value !== ALL ||
        from.value !== '' ||
        to.value !== '',
);

function clearFilters() {
    search.value = '';
    status.value = ALL;
    urgency.value = ALL;
    from.value = '';
    to.value = '';
}

const TONES: Record<string, string> = {
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    muted: 'bg-muted text-muted-foreground',
};

const URGENCY_TONES: Record<string, string> = {
    emergency: 'text-red-700 dark:text-red-400',
    urgent: 'text-amber-700 dark:text-amber-400',
    normal: 'text-muted-foreground',
};

/**
 * Paginator labels arrive with HTML entities ("&laquo; Previous"). Decoding
 * them to text keeps the links plain — no v-html on a component.
 */
function pageLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .trim();
}
</script>

<template>
    <Head title="Referrals" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Referrals</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Patients referred to other facilities, and whether they were
                    seen. Referrals are issued from the encounter.
                </p>
            </div>
            <div class="flex gap-3">
                <div class="rounded-xl border border-border bg-card px-4 py-2">
                    <p class="text-xs text-muted-foreground">Open</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.open }}
                    </p>
                </div>
                <div
                    class="rounded-xl border px-4 py-2"
                    :class="
                        summary.awaiting_feedback
                            ? 'border-amber-500/40 bg-amber-500/5'
                            : 'border-border bg-card'
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        No feedback after 14 days
                    </p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.awaiting_feedback }}
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card px-4 py-2">
                    <p class="text-xs text-muted-foreground">This month</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.this_month }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="grid gap-1.5">
                <Label class="text-xs">Search</Label>
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Patient, number or facility"
                />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Status</Label>
                <Select v-model="status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem value="open"
                            >Open (issued or accepted)</SelectItem
                        >
                        <SelectItem
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Urgency</Label>
                <Select v-model="urgency">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Any</SelectItem>
                        <SelectItem
                            v-for="u in urgencies"
                            :key="u.value"
                            :value="u.value"
                            >{{ u.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">From</Label>
                <Input v-model="from" type="date" />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">To</Label>
                <Input v-model="to" type="date" />
            </div>
            <div v-if="hasFilters" class="flex items-end">
                <Button
                    type="button"
                    variant="ghost"
                    class="text-muted-foreground"
                    @click="clearFilters"
                >
                    <X class="size-4" />
                    Clear filters
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Issued</th>
                        <th class="px-4 py-2.5 font-medium">Patient</th>
                        <th class="px-4 py-2.5 font-medium">Referred to</th>
                        <th class="px-4 py-2.5 font-medium">Urgency</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="r in props.referrals.data"
                        :key="r.id"
                        class="cursor-pointer hover:bg-muted/50"
                        @click="router.visit(r.urls.show)"
                    >
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <div>{{ r.referred_at }}</div>
                            <div
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ r.referral_number }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-medium">{{ r.patient.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ r.patient.file_number }} · {{ r.patient.sex
                                }}<template v-if="r.patient.age !== null">
                                    · {{ r.patient.age }}y</template
                                >
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-medium">
                                {{ r.destination_facility }}
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ r.destination_department ?? r.reason }}
                            </div>
                        </td>
                        <td
                            class="px-4 py-2.5 text-xs font-medium"
                            :class="URGENCY_TONES[r.urgency]"
                        >
                            {{ r.urgency_label }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="rounded-md px-1.5 py-0.5 text-xs font-medium"
                                :class="TONES[r.status_tone]"
                                >{{ r.status_label }}</span
                            >
                            <div
                                v-if="r.days_open !== null && r.days_open >= 14"
                                class="mt-0.5 text-xs text-amber-700 dark:text-amber-400"
                            >
                                {{ r.days_open }} days without feedback
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!props.referrals.data.length">
                        <td
                            colspan="5"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            <Send class="mx-auto mb-2 size-6" />
                            {{
                                hasFilters
                                    ? 'No referrals match these filters.'
                                    : 'No referrals have been issued yet.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="props.referrals.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                Showing {{ props.referrals.from ?? 0 }}–{{
                    props.referrals.to ?? 0
                }}
                of {{ props.referrals.total }}
            </p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in props.referrals.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :aria-current="link.active ? 'page' : undefined"
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border hover:bg-muted'
                        "
                    >
                        {{ pageLabel(link.label) }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border px-2 text-sm text-muted-foreground/50"
                    >
                        {{ pageLabel(link.label) }}
                    </span>
                </template>
            </div>
        </div>
    </div>
</template>
