<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, CopyCheck, ShieldAlert, Timer } from '@lucide/vue';
import { computed } from 'vue';
import DataQualityNav from '@/components/data-quality/DataQualityNav.vue';
import { index as issuesIndex, scorecard } from '@/routes/data-quality';

type DimensionRow = {
    value: string;
    label: string;
    description: string;
    icon: string;
    rules: number;
    open: number;
    population: number;
    pass_rate: number;
};

type RuleRow = {
    id: number;
    key: string;
    name: string;
    dimension: string;
    dimension_label: string;
    severity: string;
    severity_label: string;
    severity_tone: string;
    owner_role: string | null;
    open: number;
    waived: number;
    population: number;
    pass_rate: number;
};

const props = defineProps<{
    summary: {
        open: number;
        critical: number;
        waived: number;
        raised: number;
        resolved: number;
        pass_rate: number;
        records: number;
        oldest_open: string | null;
    };
    dimensions: DimensionRow[];
    rules: RuleRow[];
    trend: Array<{
        date: string;
        label: string;
        raised: number;
        resolved: number;
    }>;
    filters: { range: string; from: string; to: string; label: string };
    presets: Array<{ key: string; label: string }>;
    can: { manage_rules: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Data quality', href: '/data-quality' }],
    },
});

function applyPreset(key: string) {
    router.get(
        scorecard().url,
        { range: key },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

/** The icon each dimension names, kept here so only these four are bundled. */
const DIMENSION_ICONS: Record<string, unknown> = {
    ClipboardList,
    ShieldAlert,
    CopyCheck,
    Timer,
};

function iconFor(name: string) {
    return DIMENSION_ICONS[name] ?? ClipboardList;
}

function rateTone(rate: number): string {
    if (rate >= 98) {
        return 'text-emerald-700 dark:text-emerald-400';
    }

    return rate >= 90
        ? 'text-amber-700 dark:text-amber-400'
        : 'text-red-700 dark:text-red-400';
}

function barTone(rate: number): string {
    if (rate >= 98) {
        return 'bg-emerald-500';
    }

    return rate >= 90 ? 'bg-amber-500' : 'bg-red-500';
}

const worstFirst = computed(() =>
    [...props.rules].sort((a, b) => a.pass_rate - b.pass_rate),
);

const trendPeak = computed(() =>
    Math.max(
        1,
        ...props.trend.map((day) => Math.max(day.raised, day.resolved)),
    ),
);

const netMovement = computed(
    () => props.summary.resolved - props.summary.raised,
);
</script>

<template>
    <Head title="Data quality scorecard" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Data quality scorecard
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    How the register stands against the rules in force. Pass
                    rates are measured now; issues raised and closed cover
                    {{ filters.label }}.
                </p>
            </div>
            <div
                class="flex flex-wrap gap-1 rounded-lg border border-border bg-card p-1"
            >
                <button
                    v-for="p in presets"
                    :key="p.key"
                    type="button"
                    class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                    :class="
                        filters.range === p.key
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="applyPreset(p.key)"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <DataQualityNav current="scorecard" />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-sm text-muted-foreground">Overall pass rate</p>
                <p
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    :class="rateTone(summary.pass_rate)"
                >
                    {{ summary.pass_rate.toFixed(1) }}%
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    across {{ summary.records.toLocaleString() }} records judged
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-sm text-muted-foreground">Open issues</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ summary.open }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <template v-if="summary.critical"
                        >{{ summary.critical }} critical · </template
                    >{{ summary.waived }} waived
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-sm text-muted-foreground">Raised in period</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ summary.raised }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ summary.resolved }} closed ·
                    <span
                        :class="
                            netMovement >= 0
                                ? 'text-emerald-700 dark:text-emerald-400'
                                : 'text-red-700 dark:text-red-400'
                        "
                        >{{
                            netMovement >= 0 ? 'gaining' : 'falling behind'
                        }}</span
                    >
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-sm text-muted-foreground">Oldest open issue</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ summary.oldest_open ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">first detected</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="dimension in dimensions"
                :key="dimension.value"
                :href="
                    issuesIndex({ query: { dimension: dimension.value } }).url
                "
                class="rounded-xl border border-border bg-card p-4 transition-colors hover:bg-muted/40"
            >
                <div class="flex items-center gap-2">
                    <component
                        :is="iconFor(dimension.icon)"
                        class="size-4 text-muted-foreground"
                    />
                    <p class="text-sm font-medium">{{ dimension.label }}</p>
                </div>
                <p
                    class="mt-2 text-xl font-semibold tabular-nums"
                    :class="rateTone(dimension.pass_rate)"
                >
                    {{ dimension.pass_rate.toFixed(1) }}%
                </p>
                <div class="mt-2 h-1.5 w-full rounded-full bg-muted">
                    <div
                        class="h-1.5 rounded-full"
                        :class="barTone(dimension.pass_rate)"
                        :style="{ width: `${dimension.pass_rate}%` }"
                    />
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ dimension.open }} open ·
                    {{ dimension.rules }}
                    {{ dimension.rules === 1 ? 'rule' : 'rules' }}
                </p>
            </Link>
        </div>

        <div class="rounded-xl border border-border bg-card p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-medium">Raised against closed</h2>
                <p class="text-xs text-muted-foreground">{{ filters.label }}</p>
            </div>
            <div class="mt-4 flex items-end gap-1 overflow-x-auto">
                <div
                    v-for="day in trend"
                    :key="day.date"
                    class="flex min-w-3 flex-1 flex-col items-center gap-1"
                    :title="`${day.label}: ${day.raised} raised, ${day.resolved} closed`"
                >
                    <div
                        class="flex h-24 w-full items-end justify-center gap-0.5"
                    >
                        <div
                            class="w-1/2 rounded-t bg-red-500/70"
                            :style="{
                                height: `${(day.raised / trendPeak) * 100}%`,
                            }"
                        />
                        <div
                            class="w-1/2 rounded-t bg-emerald-500/70"
                            :style="{
                                height: `${(day.resolved / trendPeak) * 100}%`,
                            }"
                        />
                    </div>
                </div>
            </div>
            <div class="mt-3 flex gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5"
                    ><span class="size-2 rounded-full bg-red-500/70" />
                    Raised</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="size-2 rounded-full bg-emerald-500/70" />
                    Closed</span
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Rule</th>
                        <th class="px-4 py-2.5 font-medium">Dimension</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Records judged
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Failing
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Waived
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Pass rate
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="rule in worstFirst"
                        :key="rule.id"
                        class="cursor-pointer hover:bg-muted/50"
                        @click="
                            router.visit(
                                issuesIndex({ query: { rule: rule.id } }).url,
                            )
                        "
                    >
                        <td class="px-4 py-2.5 font-medium">{{ rule.name }}</td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ rule.dimension_label }} ·
                            {{ rule.severity_label }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ rule.population.toLocaleString() }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ rule.open }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right text-muted-foreground tabular-nums"
                        >
                            {{ rule.waived }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right font-medium tabular-nums"
                            :class="rateTone(rule.pass_rate)"
                        >
                            {{ rule.pass_rate.toFixed(1) }}%
                        </td>
                    </tr>
                    <tr v-if="!rules.length">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            No rules are in force.
                            <Link
                                v-if="can.manage_rules"
                                href="/data-quality/rules"
                                class="underline"
                                >Switch some on</Link
                            >.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
