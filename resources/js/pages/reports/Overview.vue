<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    FlaskConical,
    Package,
    Pill,
    Stethoscope,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import HorizontalBars from '@/components/charts/HorizontalBars.vue';
import LineChart from '@/components/charts/LineChart.vue';
import StatTile from '@/components/charts/StatTile.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Metric = { value: number; previous: number | null };
type Series = {
    granularity: string;
    points: Array<{ label: string; value: number }>;
};
type Composition = Array<{ key: string; label: string; value: number }>;
type Bars = Array<{ label: string; value: number }>;

const props = defineProps<{
    filters: { range: string; from: string; to: string; label: string };
    presets: Array<{ key: string; label: string }>;
    report: {
        kpis: {
            revenue: Metric;
            patients: Metric;
            visits: Metric;
            consultations: Metric;
            labOrders: Metric;
            prescriptions: Metric;
            appointments: Metric;
            outstanding: Metric;
        };
        revenueTrend: Series;
        visitsTrend: Series;
        revenueByMethod: Composition;
        appointmentsByStatus: Composition;
        labByStatus: Composition;
        servicePointThroughput: Bars;
        patientFlow: Bars;
        topDiagnoses: Bars;
        topDispensed: Array<{
            name: string;
            quantity: number;
            revenue: number;
        }>;
        lowStock: Array<{
            name: string;
            code: string;
            on_hand: number;
            reorder_level: number;
            unit: string | null;
        }>;
    };
    generatedAt: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Reports', href: '/reports' },
            { title: 'Executive Overview', href: '/reports/overview' },
        ],
    },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

function moneyCompact(v: number): string {
    if (Math.abs(v) >= 1_000_000) {
        return `₦${(v / 1_000_000).toFixed(1)}M`;
    }

    if (Math.abs(v) >= 1_000) {
        return `₦${(v / 1_000).toFixed(1)}k`;
    }

    return `₦${Math.round(v)}`;
}

function pct(m: Metric): number | null {
    if (m.previous === null) {
        return null;
    }

    if (m.previous === 0) {
        return m.value > 0 ? 100 : null;
    }

    return Math.round(((m.value - m.previous) / m.previous) * 100);
}

const k = props.report.kpis;

const tiles = computed(() => [
    {
        label: 'Revenue',
        value: money(k.revenue.value),
        delta: pct(k.revenue),
        icon: Banknote,
        sub: 'Payments received',
    },
    {
        label: 'New patients',
        value: k.patients.value.toLocaleString(),
        delta: pct(k.patients),
        icon: Users,
        sub: 'Registrations',
    },
    {
        label: 'Visits',
        value: k.visits.value.toLocaleString(),
        delta: pct(k.visits),
        icon: CalendarDays,
        sub: 'Opened in range',
    },
    {
        label: 'Consultations',
        value: k.consultations.value.toLocaleString(),
        delta: pct(k.consultations),
        icon: Stethoscope,
        sub: 'Signed off',
    },
    {
        label: 'Lab orders',
        value: k.labOrders.value.toLocaleString(),
        delta: pct(k.labOrders),
        icon: FlaskConical,
        sub: 'Requisitions',
    },
    {
        label: 'Prescriptions',
        value: k.prescriptions.value.toLocaleString(),
        delta: pct(k.prescriptions),
        icon: Pill,
        sub: 'Dispensed',
    },
    {
        label: 'Appointments',
        value: k.appointments.value.toLocaleString(),
        delta: pct(k.appointments),
        icon: CalendarDays,
        sub: 'Scheduled',
    },
    {
        label: 'Outstanding',
        value: money(k.outstanding.value),
        delta: null,
        icon: Wallet,
        sub: 'Unpaid balance',
        invert: true,
    },
]);

function applyPreset(key: string) {
    router.get(
        '/reports/overview',
        { range: key },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyCustom() {
    router.get(
        '/reports/overview',
        { range: 'custom', from: from.value, to: to.value },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <!-- Header + range toolbar -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Reports</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Executive overview · {{ filters.label }}
                </p>
            </div>

            <div
                class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center"
            >
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
                <div class="flex items-center gap-1.5">
                    <Input v-model="from" type="date" class="h-8 w-[9.5rem]" />
                    <span class="text-muted-foreground">–</span>
                    <Input v-model="to" type="date" class="h-8 w-[9.5rem]" />
                    <Button size="sm" variant="outline" @click="applyCustom"
                        >Apply</Button
                    >
                </div>
            </div>
        </div>

        <!-- KPI tiles -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile
                v-for="t in tiles"
                :key="t.label"
                :label="t.label"
                :value="t.value"
                :delta="t.delta"
                :invert-delta="t.invert"
                :sub="t.sub"
                :icon="t.icon"
            />
        </div>

        <!-- Revenue trend -->
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="mb-3 flex items-start justify-between">
                <div>
                    <h2 class="text-sm font-semibold">Revenue</h2>
                    <p class="text-xs text-muted-foreground">
                        Payments received per
                        {{ report.revenueTrend.granularity }}
                    </p>
                </div>
                <span class="text-lg font-semibold tabular-nums">{{
                    money(report.kpis.revenue.value)
                }}</span>
            </div>
            <LineChart
                :points="report.revenueTrend.points"
                :format-value="moneyCompact"
            />
        </div>

        <!-- Visits + revenue by method -->
        <div class="grid gap-4 lg:grid-cols-3">
            <div
                class="rounded-xl border border-border bg-card p-5 lg:col-span-2"
            >
                <h2 class="mb-3 text-sm font-semibold">Patient visits</h2>
                <BarChart :bars="report.visitsTrend.points" />
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold">
                    Revenue by payment method
                </h2>
                <DonutChart
                    :segments="report.revenueByMethod"
                    :format-value="moneyCompact"
                    center-label="Total"
                />
            </div>
        </div>

        <!-- Appointments + lab status -->
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold">
                    Appointments by status
                </h2>
                <DonutChart
                    :segments="report.appointmentsByStatus"
                    center-label="Appointments"
                />
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold">Lab orders by status</h2>
                <DonutChart
                    :segments="report.labByStatus"
                    center-label="Lab orders"
                />
            </div>
        </div>

        <!-- Throughput + diagnoses + flow -->
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold">
                    Service point throughput
                </h2>
                <HorizontalBars :items="report.servicePointThroughput" />
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-sm font-semibold">Top diagnoses</h2>
                <HorizontalBars :items="report.topDiagnoses" colorful />
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold">Patient flow</h2>
                <DonutChart
                    :segments="report.patientFlow"
                    center-label="Queue hops"
                />
            </div>
        </div>

        <!-- Tables -->
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <Pill class="size-4 text-muted-foreground" /> Top dispensed
                    items
                </h2>
                <table v-if="report.topDispensed.length" class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="pb-2 font-medium">Item</th>
                            <th class="pb-2 text-right font-medium">Qty</th>
                            <th class="pb-2 text-right font-medium">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in report.topDispensed"
                            :key="item.name"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td class="py-2 pr-2">{{ item.name }}</td>
                            <td class="py-2 text-right tabular-nums">
                                {{ item.quantity }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ money(item.revenue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-else
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    No items dispensed in this period.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <Package class="size-4 text-muted-foreground" /> Low stock
                    alerts
                </h2>
                <table v-if="report.lowStock.length" class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="pb-2 font-medium">Item</th>
                            <th class="pb-2 text-right font-medium">On hand</th>
                            <th class="pb-2 text-right font-medium">
                                Reorder at
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in report.lowStock"
                            :key="item.code"
                            class="border-b border-border/60 last:border-0"
                        >
                            <td class="py-2 pr-2">
                                {{ item.name }}
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                    >· {{ item.code }}</span
                                >
                            </td>
                            <td class="py-2 text-right">
                                <span
                                    class="inline-flex items-center rounded-md bg-amber-500/10 px-1.5 py-0.5 text-xs font-medium text-amber-700 tabular-nums dark:text-amber-400"
                                    >{{ item.on_hand
                                    }}{{
                                        item.unit ? ' ' + item.unit : ''
                                    }}</span
                                >
                            </td>
                            <td
                                class="py-2 text-right text-muted-foreground tabular-nums"
                            >
                                {{ item.reorder_level }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-else
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    All stock is above reorder levels.
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-muted-foreground">
            Generated {{ generatedAt }}
        </p>
    </div>
</template>
