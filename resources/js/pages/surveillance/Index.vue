<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Radar, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import SurveillanceNav from '@/components/surveillance/SurveillanceNav.vue';
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
import { index as casesIndex } from '@/routes/surveillance';

type CaseRow = {
    id: number;
    detected_at: string;
    disease: string;
    category: 'immediate' | 'weekly';
    category_label: string;
    icd_code: string | null;
    patient: {
        id: number;
        name: string;
        file_number: string;
        sex: string;
        age: number | null;
        lga: string | null;
    };
    classification: string;
    classification_label: string;
    classification_tone: string;
    outcome_label: string;
    notification_status: string;
    notification_label: string;
    notification_tone: string;
    overdue: boolean;
    href: string;
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
    cases: Paginated<CaseRow>;
    filters: {
        status: string;
        classification: string;
        disease: number | null;
        search: string;
        from: string;
        to: string;
    };
    summary: {
        pending: number;
        overdue: number;
        this_week: number;
        week_label: string;
    };
    diseases: Array<{ id: number; name: string }>;
    statuses: Option[];
    classifications: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Case surveillance', href: '/surveillance' }],
    },
});

const ALL = 'all';

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status || ALL);
const classification = ref(props.filters.classification || ALL);
const disease = ref(
    props.filters.disease ? String(props.filters.disease) : ALL,
);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function apply() {
    router.get(
        casesIndex().url,
        {
            search: search.value || undefined,
            status: status.value === ALL ? undefined : status.value,
            classification:
                classification.value === ALL ? undefined : classification.value,
            disease: disease.value === ALL ? undefined : disease.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watchDebounced(search, apply, { debounce: 300 });
watch([status, classification, disease, from, to], apply);

const hasFilters = computed(
    () =>
        search.value !== '' ||
        status.value !== ALL ||
        classification.value !== ALL ||
        disease.value !== ALL ||
        from.value !== '' ||
        to.value !== '',
);

function clearFilters() {
    search.value = '';
    status.value = ALL;
    classification.value = ALL;
    disease.value = ALL;
    from.value = '';
    to.value = '';
}

const TONES: Record<string, string> = {
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    muted: 'bg-muted text-muted-foreground',
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
    <Head title="Case surveillance" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Case surveillance
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    IDSR cases opened automatically when a notifiable disease is
                    coded on a diagnosis. Immediately notifiable cases must
                    reach the LGA DSNO within 24 hours.
                </p>
            </div>
            <div class="flex gap-3">
                <div
                    class="rounded-xl border px-4 py-2"
                    :class="
                        summary.pending
                            ? 'border-red-500/40 bg-red-500/5'
                            : 'border-border bg-card'
                    "
                >
                    <p class="text-xs text-muted-foreground">
                        Awaiting notification
                    </p>
                    <p
                        class="text-xl font-semibold tabular-nums"
                        :class="
                            summary.pending
                                ? 'text-red-700 dark:text-red-400'
                                : ''
                        "
                    >
                        {{ summary.pending }}
                        <span v-if="summary.overdue" class="text-xs font-medium"
                            >· {{ summary.overdue }} overdue</span
                        >
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card px-4 py-2">
                    <p class="text-xs text-muted-foreground">
                        This week · {{ summary.week_label }}
                    </p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.this_week }}
                    </p>
                </div>
            </div>
        </div>

        <SurveillanceNav current="cases" />

        <div
            class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div class="grid gap-1.5">
                <Label class="text-xs">Patient</Label>
                <Input
                    v-model="search"
                    type="search"
                    placeholder="File number or name"
                />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Disease</Label>
                <Select v-model="disease">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All diseases" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All diseases</SelectItem>
                        <SelectItem
                            v-for="d in diseases"
                            :key="d.id"
                            :value="String(d.id)"
                        >
                            {{ d.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Notification</Label>
                <Select v-model="status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Any</SelectItem>
                        <SelectItem
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Classification</Label>
                <Select v-model="classification">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Open cases" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Open cases</SelectItem>
                        <SelectItem
                            v-for="c in classifications"
                            :key="c.value"
                            :value="c.value"
                        >
                            {{ c.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Detected from</Label>
                <Input v-model="from" type="date" />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">To</Label>
                <Input v-model="to" type="date" />
            </div>
            <div class="flex items-end">
                <Button
                    v-if="hasFilters"
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
                        <th class="px-4 py-2.5 font-medium">Detected</th>
                        <th class="px-4 py-2.5 font-medium">Disease</th>
                        <th class="px-4 py-2.5 font-medium">Patient</th>
                        <th class="px-4 py-2.5 font-medium">Classification</th>
                        <th class="px-4 py-2.5 font-medium">Outcome</th>
                        <th class="px-4 py-2.5 font-medium">DSNO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="c in props.cases.data"
                        :key="c.id"
                        class="cursor-pointer hover:bg-muted/50"
                        @click="router.visit(c.href)"
                    >
                        <td
                            class="px-4 py-2.5 whitespace-nowrap text-muted-foreground"
                        >
                            {{ c.detected_at }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5 font-medium">
                                <AlertTriangle
                                    v-if="c.category === 'immediate'"
                                    class="size-3.5 text-red-600 dark:text-red-400"
                                />
                                {{ c.disease }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ c.category_label
                                }}<template v-if="c.icd_code">
                                    · {{ c.icd_code }}</template
                                >
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-medium">{{ c.patient.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ c.patient.file_number }} · {{ c.patient.sex
                                }}<template v-if="c.patient.age !== null">
                                    · {{ c.patient.age }}y</template
                                ><template v-if="c.patient.lga">
                                    · {{ c.patient.lga }}</template
                                >
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="rounded-md px-1.5 py-0.5 text-xs font-medium"
                                :class="TONES[c.classification_tone]"
                                >{{ c.classification_label }}</span
                            >
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ c.outcome_label }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="rounded-md px-1.5 py-0.5 text-xs font-medium"
                                :class="TONES[c.notification_tone]"
                                >{{ c.notification_label }}</span
                            >
                            <div
                                v-if="c.overdue"
                                class="mt-0.5 text-xs font-medium text-red-700 dark:text-red-400"
                            >
                                Past deadline
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!props.cases.data.length">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            <Radar class="mx-auto mb-2 size-6" />
                            {{
                                hasFilters
                                    ? 'No cases match these filters.'
                                    : 'No notifiable disease has been coded yet.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="props.cases.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                Showing {{ props.cases.from ?? 0 }}–{{ props.cases.to ?? 0 }} of
                {{ props.cases.total }}
            </p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in props.cases.links" :key="i">
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
