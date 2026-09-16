<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CheckCircle2,
    RefreshCw,
    ShieldOff,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import DataQualityNav from '@/components/data-quality/DataQualityNav.vue';
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
import { index as issuesIndex } from '@/routes/data-quality';
import { recheck, waive } from '@/routes/data-quality/issues';

type IssueRow = {
    id: number;
    rule: string;
    dimension: string;
    dimension_label: string;
    severity: string;
    severity_label: string;
    severity_tone: string;
    status: string;
    status_label: string;
    status_tone: string;
    summary: string;
    detail: Record<string, unknown> | null;
    record: string;
    href: string | null;
    patient: { name: string; file_number: string; href: string } | null;
    owner: string | null;
    first_detected_at: string;
    age_days: number;
    resolution: string | null;
    resolved_at: string | null;
    resolved_by: string | null;
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
    issues: Paginated<IssueRow>;
    filters: {
        dimension: string;
        severity: string;
        status: string;
        rule: number | null;
        owner: string;
        search: string;
    };
    summary: {
        open: number;
        critical: number;
        mine: number;
        waived: number;
    };
    rules: Array<{ id: number; name: string }>;
    dimensions: Option[];
    severities: Option[];
    statuses: Option[];
    can: { waive: boolean; manage_rules: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Data quality', href: '/data-quality' }],
    },
});

const ALL = 'all';

const search = ref(props.filters.search ?? '');
const dimension = ref(props.filters.dimension || ALL);
const severity = ref(props.filters.severity || ALL);
const status = ref(props.filters.status || 'open');
const rule = ref(props.filters.rule ? String(props.filters.rule) : ALL);
const owner = ref(props.filters.owner || ALL);

function apply() {
    router.get(
        issuesIndex().url,
        {
            search: search.value || undefined,
            dimension: dimension.value === ALL ? undefined : dimension.value,
            severity: severity.value === ALL ? undefined : severity.value,
            status: status.value === 'open' ? undefined : status.value,
            rule: rule.value === ALL ? undefined : rule.value,
            owner: owner.value === ALL ? undefined : owner.value,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watchDebounced(search, apply, { debounce: 300 });
watch([dimension, severity, status, rule, owner], apply);

const hasFilters = computed(
    () =>
        search.value !== '' ||
        dimension.value !== ALL ||
        severity.value !== ALL ||
        status.value !== 'open' ||
        rule.value !== ALL ||
        owner.value !== ALL,
);

function clearFilters() {
    search.value = '';
    dimension.value = ALL;
    severity.value = ALL;
    status.value = 'open';
    rule.value = ALL;
    owner.value = ALL;
}

const TONES: Record<string, string> = {
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    muted: 'bg-muted text-muted-foreground',
};

const rechecking = ref<number | null>(null);

function recheckIssue(issue: IssueRow) {
    rechecking.value = issue.id;

    router.post(
        recheck(issue.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                rechecking.value = null;
            },
        },
    );
}

const waiving = ref<IssueRow | null>(null);
const waiveForm = useForm({ reason: '' });

function openWaive(issue: IssueRow) {
    waiveForm.reset();
    waiveForm.clearErrors();
    waiving.value = issue;
}

function submitWaive() {
    if (!waiving.value) {
        return;
    }

    waiveForm.post(waive(waiving.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            waiving.value = null;
        },
    });
}

const isOpenQueue = computed(() => status.value === 'open');

function pageLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .trim();
}

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';
</script>

<template>
    <Head title="Data quality" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Data quality
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Records failing one of the facility's rules, with the screen
                    that puts each one right. Issues close on their own once the
                    record passes.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div
                    class="rounded-xl border px-4 py-2"
                    :class="
                        summary.critical
                            ? 'border-red-500/40 bg-red-500/5'
                            : 'border-border bg-card'
                    "
                >
                    <p class="text-xs text-muted-foreground">Open issues</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.open }}
                        <span
                            v-if="summary.critical"
                            class="text-xs font-medium text-red-700 dark:text-red-400"
                            >· {{ summary.critical }} critical</span
                        >
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card px-4 py-2">
                    <p class="text-xs text-muted-foreground">Yours to clear</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.mine }}
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card px-4 py-2">
                    <p class="text-xs text-muted-foreground">Waived</p>
                    <p class="text-xl font-semibold tabular-nums">
                        {{ summary.waived }}
                    </p>
                </div>
            </div>
        </div>

        <DataQualityNav current="issues" />

        <div
            class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
        >
            <div class="grid gap-1.5">
                <Label class="text-xs">Search</Label>
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Patient or wording"
                />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Rule</Label>
                <Select v-model="rule">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All rules" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All rules</SelectItem>
                        <SelectItem
                            v-for="r in rules"
                            :key="r.id"
                            :value="String(r.id)"
                        >
                            {{ r.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Dimension</Label>
                <Select v-model="dimension">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Any</SelectItem>
                        <SelectItem
                            v-for="d in dimensions"
                            :key="d.value"
                            :value="d.value"
                        >
                            {{ d.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Severity</Label>
                <Select v-model="severity">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Any</SelectItem>
                        <SelectItem
                            v-for="s in severities"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Status</Label>
                <Select v-model="status">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Open" />
                    </SelectTrigger>
                    <SelectContent>
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
                <Label class="text-xs">Owner</Label>
                <Select v-model="owner">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Everyone" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Everyone</SelectItem>
                        <SelectItem value="mine">Mine to clear</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex items-end xl:col-span-6">
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
                        <th class="px-4 py-2.5 font-medium">Issue</th>
                        <th class="px-4 py-2.5 font-medium">Record</th>
                        <th class="px-4 py-2.5 font-medium">Rule</th>
                        <th class="px-4 py-2.5 font-medium">Severity</th>
                        <th class="px-4 py-2.5 font-medium">Since</th>
                        <th class="px-4 py-2.5 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="issue in props.issues.data"
                        :key="issue.id"
                        class="align-top"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ issue.summary }}</div>
                            <div
                                v-if="issue.status !== 'open'"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ issue.status_label }}
                                <template v-if="issue.resolved_at">
                                    · {{ issue.resolved_at }}</template
                                ><template v-if="issue.resolved_by">
                                    · {{ issue.resolved_by }}</template
                                >
                                <div v-if="issue.resolution" class="italic">
                                    {{ issue.resolution }}
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                v-if="issue.patient"
                                :href="issue.patient.href"
                                class="font-medium hover:underline"
                            >
                                {{ issue.patient.name }}
                            </Link>
                            <div v-else class="font-medium">
                                {{ issue.record }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                <template v-if="issue.patient"
                                    >{{ issue.patient.file_number }} ·
                                </template>
                                {{ issue.record }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ issue.rule }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ issue.dimension_label
                                }}<template v-if="issue.owner">
                                    · {{ issue.owner }}</template
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-md px-1.5 py-0.5 text-xs font-medium"
                                :class="TONES[issue.severity_tone]"
                                >{{ issue.severity_label }}</span
                            >
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>{{ issue.first_detected_at }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ issue.age_days }}
                                {{ issue.age_days === 1 ? 'day' : 'days' }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <Button
                                    v-if="issue.href"
                                    as-child
                                    size="sm"
                                    variant="outline"
                                >
                                    <Link :href="issue.href">
                                        <ArrowUpRight class="size-4" />
                                        Open record
                                    </Link>
                                </Button>
                                <Button
                                    v-if="isOpenQueue"
                                    size="sm"
                                    variant="ghost"
                                    :disabled="rechecking === issue.id"
                                    @click="recheckIssue(issue)"
                                >
                                    <RefreshCw
                                        class="size-4"
                                        :class="
                                            rechecking === issue.id
                                                ? 'animate-spin'
                                                : ''
                                        "
                                    />
                                    Re-check
                                </Button>
                                <Button
                                    v-if="isOpenQueue && can.waive"
                                    size="sm"
                                    variant="ghost"
                                    class="text-muted-foreground"
                                    @click="openWaive(issue)"
                                >
                                    <ShieldOff class="size-4" />
                                    Waive
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!props.issues.data.length">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            <CheckCircle2 class="mx-auto mb-2 size-6" />
                            {{
                                hasFilters
                                    ? 'No issues match these filters.'
                                    : 'Nothing outstanding: every record passes every rule in force.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="props.issues.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                Showing {{ props.issues.from ?? 0 }}–{{
                    props.issues.to ?? 0
                }}
                of {{ props.issues.total }}
            </p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in props.issues.links" :key="i">
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

        <Dialog
            :open="waiving !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) waiving = null;
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Waive this issue</DialogTitle>
                    <DialogDescription>
                        {{ waiving?.summary }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitWaive">
                    <div class="grid gap-1.5">
                        <Label for="waive-reason"
                            >Why is the record being left as it is? *</Label
                        >
                        <textarea
                            id="waive-reason"
                            v-model="waiveForm.reason"
                            rows="3"
                            :class="textareaClass"
                            placeholder="e.g. Patient has no phone; address confirmed verbally and recorded in the notes."
                        />
                        <p
                            v-if="waiveForm.errors.reason"
                            class="text-xs text-red-600 dark:text-red-400"
                        >
                            {{ waiveForm.errors.reason }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            The reason is kept with the issue and in the audit
                            trail.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="waiving = null"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="waiveForm.processing">
                            Waive issue
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
