<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download } from '@lucide/vue';

type Column = { key: string; label: string; align: 'left' | 'right' };

const props = defineProps<{
    report: { key: string; name: string; description: string; category: string };
    columns: Column[];
    rows: Array<Record<string, string>>;
    summary: Array<{ label: string; value: string }>;
    filters: { range: string; from: string; to: string; label: string };
    presets: Array<{ key: string; label: string }>;
    exportUrl: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: '/reports' }] },
});

function applyPreset(key: string) {
    router.get(
        `/reports/run/${props.report.key}`,
        { range: key },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${report.name} — Reports`" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <!-- Header -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-3">
                <Link
                    href="/reports"
                    class="mt-0.5 flex size-8 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-muted"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ report.name }}</h1>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                            >{{ report.category }}</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ report.description }} · {{ filters.label }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-1 rounded-lg border border-border bg-card p-1">
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
                <a
                    :href="exportUrl"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-card px-3 text-xs font-medium transition-colors hover:bg-muted"
                >
                    <Download class="size-4" /> Export CSV
                </a>
            </div>
        </div>

        <!-- Summary tiles -->
        <div v-if="summary.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="s in summary"
                :key="s.label"
                class="rounded-xl border border-border bg-card p-4"
            >
                <p class="text-sm text-muted-foreground">{{ s.label }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                    {{ s.value }}
                </p>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-border bg-card">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 class="text-sm font-semibold">Results</h2>
                <span class="text-xs text-muted-foreground">{{ rows.length }} rows</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs text-muted-foreground">
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-2.5 font-medium whitespace-nowrap"
                                :class="col.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, i) in rows"
                            :key="i"
                            class="border-b border-border/60 last:border-0 hover:bg-muted/40"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-2.5 whitespace-nowrap"
                                :class="[
                                    col.align === 'right' ? 'text-right tabular-nums' : 'text-left',
                                    col.key === columns[0].key ? 'font-medium' : 'text-muted-foreground',
                                ]"
                            >
                                {{ row[col.key] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    v-if="!rows.length"
                    class="p-12 text-center text-sm text-muted-foreground"
                >
                    No data for this period.
                </div>
            </div>
        </div>
    </div>
</template>
