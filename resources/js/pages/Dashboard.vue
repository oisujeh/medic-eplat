<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import * as LucideIcons from '@lucide/vue';
import {
    AlertTriangle,
    Banknote,
    BedDouble,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    CheckCircle2,
    FlaskConical,
    HandCoins,
    HeartPulse,
    LayoutGrid,
    ListChecks,
    Megaphone,
    Package,
    Pill,
    ReceiptText,
    Stethoscope,
    UserPlus,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import StatTile from '@/components/charts/StatTile.vue';
import SectionCard from '@/components/dashboard/SectionCard.vue';
import WorklistRow from '@/components/dashboard/WorklistRow.vue';
import { naira } from '@/lib/money';
import type { SharedData } from '@/types';

type Tile = {
    key: string;
    label: string;
    value: number;
    format: 'number' | 'money' | 'minutes' | 'percent';
    icon: string;
    href: string;
    sub: string | null;
};

type QueueRow = {
    id: number;
    patient: string;
    file_number: string;
    service_point: string;
    priority: 'normal' | 'urgent' | 'emergency';
    waited: string;
    href: string;
};

type AppointmentRow = {
    id: number;
    time: string;
    patient: string;
    file_number: string;
    provider: string | null;
    service_point: string;
    status: string;
    status_label: string;
    href: string;
};

type Sections = {
    queues: {
        points: Array<{
            slug: string;
            name: string;
            waiting: number;
            in_service: number;
            href: string;
        }>;
        href: string;
    } | null;
    clinical: {
        worklist: QueueRow[];
        waiting_count: number;
        appointments: AppointmentRow[];
        seen_today: number;
        href: string;
    } | null;
    nursing: {
        worklist: QueueRow[];
        waiting_count: number;
        href: string;
    } | null;
    appointments: {
        rows: AppointmentRow[];
        count: number;
        href: string;
    } | null;
    laboratory: {
        counts: Array<{
            key: string;
            label: string;
            value: number;
            href: string;
        }>;
        worklist: Array<{
            id: number;
            accession_number: string;
            patient: string;
            file_number: string;
            priority: 'normal' | 'urgent' | 'emergency';
            status: string;
            status_label: string;
            age: string;
            href: string;
        }>;
        active_count: number;
        href: string;
    } | null;
    pharmacy: {
        worklist: QueueRow[];
        waiting_count: number;
        dispensed_today: number;
        href: string;
    } | null;
    admissions: {
        wards: Array<{
            id: number;
            name: string;
            code: string;
            occupied: number;
            available: number;
            out_of_service: number;
            href: string;
        }>;
        admitted_now: number;
        pending_count: number;
        pending: Array<{
            id: number;
            patient: string;
            file_number: string;
            diagnosis: string;
            age: string;
            href: string;
        }>;
        href: string;
    } | null;
    billing: {
        unpaid: Array<{
            id: number;
            patient: string;
            file_number: string;
            status: string;
            total: number;
            paid: number;
            balance: number;
            age: string;
            href: string;
        }>;
        unpaid_count: number;
        collected_today: number;
        my_collected_today: number;
        by_method: Array<{ label: string; value: number }>;
        href: string;
    } | null;
    claims: {
        draft_count: number;
        receivable_count: number;
        receivable_amount: number;
        awaiting: Array<{
            id: number;
            claim_number: string;
            patient: string;
            payer: string;
            amount: number;
            age: string;
            href: string;
        }>;
        by_payer: Array<{ label: string; value: number }>;
        href: string;
    } | null;
    management: {
        period: string;
        visits: number;
        new_patients: number;
        consultations: number;
        revenue: number;
        outstanding: number;
        overview_href: string;
        reports_href: string;
    } | null;
};

type Alert = {
    key: string;
    label: string;
    sub: string;
    count: number;
    tone: 'red' | 'amber' | 'blue' | 'violet' | 'green';
    href: string;
};

const props = defineProps<{
    today: string;
    home: { tiles: Tile[]; sections: Sections; alerts: Alert[] };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }] },
});

const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const modules = computed(() => page.props.auth.modules ?? []);
const notice = computed(() => page.props.facility.notice ?? null);

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
});

const icons = LucideIcons as unknown as Record<string, LucideIcon>;

function resolveIcon(name: string | null): LucideIcon {
    return (name && icons[name]) || LayoutGrid;
}

function tileValue(tile: Tile): string {
    switch (tile.format) {
        case 'money':
            return naira(tile.value, 0);
        case 'minutes':
            return `${tile.value} min`;
        case 'percent':
            return `${tile.value}%`;
        default:
            return tile.value.toLocaleString();
    }
}

const PRIORITY_TONES: Record<string, 'red' | 'amber' | 'muted'> = {
    emergency: 'red',
    urgent: 'amber',
    normal: 'muted',
};

const PRIORITY_LABELS: Record<string, string | null> = {
    emergency: 'Emergency',
    urgent: 'Urgent',
    normal: null,
};

const ALERT_TONES: Record<string, string> = {
    red: 'bg-red-500/10 text-red-600 dark:text-red-400',
    amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    blue: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    green: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
};

// Quick actions, filtered to modules the signed-in user can actually reach.
const ALL_ACTIONS: Array<{
    slug: string;
    label: string;
    icon: LucideIcon;
    href: string;
}> = [
    {
        slug: 'registration',
        label: 'Register patient',
        icon: UserPlus,
        href: '/registration',
    },
    {
        slug: 'appointments',
        label: 'Book appointment',
        icon: CalendarPlus,
        href: '/appointments',
    },
    {
        slug: 'queues',
        label: 'Service queues',
        icon: ListChecks,
        href: '/queues',
    },
    {
        slug: 'clinical',
        label: 'Consultations',
        icon: Stethoscope,
        href: '/clinical',
    },
    { slug: 'nursing', label: 'Nursing', icon: HeartPulse, href: '/nursing' },
    {
        slug: 'laboratory',
        label: 'Laboratory',
        icon: FlaskConical,
        href: '/laboratory',
    },
    { slug: 'pharmacy', label: 'Pharmacy', icon: Pill, href: '/pharmacy' },
    {
        slug: 'admissions',
        label: 'Wards',
        icon: BedDouble,
        href: '/admissions',
    },
    { slug: 'billing', label: 'Billing', icon: ReceiptText, href: '/billing' },
    { slug: 'claims', label: 'Claims', icon: HandCoins, href: '/claims' },
    {
        slug: 'inventory',
        label: 'Inventory',
        icon: Package,
        href: '/inventory',
    },
    { slug: 'reports', label: 'Reports', icon: ChartColumn, href: '/reports' },
];

const quickActions = computed(() =>
    ALL_ACTIONS.filter((a) => modules.value.some((m) => m.slug === a.slug)),
);

const sections = computed(() => props.home.sections);

const hasWorkPanels = computed(
    () =>
        sections.value.clinical !== null ||
        sections.value.nursing !== null ||
        sections.value.appointments !== null ||
        sections.value.laboratory !== null ||
        sections.value.pharmacy !== null ||
        sections.value.admissions !== null ||
        sections.value.billing !== null ||
        sections.value.claims !== null,
);

const BED_BAR = {
    occupied: 'bg-primary',
    available: 'bg-emerald-500/60',
    out: 'bg-muted-foreground/30',
};

function bedTotal(w: {
    occupied: number;
    available: number;
    out_of_service: number;
}) {
    return w.occupied + w.available + w.out_of_service;
}

function pctOf(part: number, whole: number): string {
    return whole > 0 ? `${(part / whole) * 100}%` : '0%';
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ greeting }}, {{ firstName }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ today }}
                    <template v-if="page.props.facility.name">
                        · {{ page.props.facility.name }}
                    </template>
                </p>
            </div>
            <div v-if="quickActions.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="action in quickActions"
                    :key="action.slug"
                    :href="action.href"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium transition-colors hover:border-primary/40 hover:bg-muted"
                >
                    <component
                        :is="action.icon"
                        class="size-3.5 text-primary"
                    />
                    {{ action.label }}
                </Link>
            </div>
        </div>

        <!-- Facility notice -->
        <div
            v-if="notice"
            class="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm"
        >
            <Megaphone
                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
            />
            <p class="whitespace-pre-line">{{ notice }}</p>
        </div>

        <!-- Today strip -->
        <div
            v-if="home.tiles.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Link
                v-for="tile in home.tiles"
                :key="tile.key"
                :href="tile.href"
                class="block"
            >
                <StatTile
                    :label="tile.label"
                    :value="tileValue(tile)"
                    :sub="tile.sub"
                    :icon="resolveIcon(tile.icon)"
                    class="h-full transition-colors hover:border-primary/40"
                />
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Work panels -->
            <div class="flex flex-col gap-4 lg:col-span-2">
                <!-- Clinical -->
                <SectionCard
                    v-if="sections.clinical"
                    title="Waiting for you"
                    :icon="Stethoscope"
                    :count="sections.clinical.waiting_count"
                    :href="sections.clinical.href"
                    link-label="Open consultations"
                    :sub="`${sections.clinical.seen_today} seen today`"
                >
                    <ul
                        v-if="sections.clinical.worklist.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="row in sections.clinical.worklist"
                            :key="row.id"
                            :href="row.href"
                            :primary="row.patient"
                            :secondary="`${row.file_number} · ${row.service_point}`"
                            :badge="PRIORITY_LABELS[row.priority]"
                            :badge-tone="PRIORITY_TONES[row.priority]"
                            :meta="row.waited"
                            meta-sub="waiting"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No one is waiting for you right now.
                    </p>

                    <template v-if="sections.clinical.appointments.length">
                        <h3
                            class="mt-4 mb-1 text-xs font-medium text-muted-foreground"
                        >
                            Your appointments today
                        </h3>
                        <ul class="divide-y divide-border/60">
                            <WorklistRow
                                v-for="a in sections.clinical.appointments"
                                :key="a.id"
                                :href="a.href"
                                :primary="a.patient"
                                :secondary="`${a.file_number} · ${a.service_point}`"
                                :badge="
                                    a.status === 'checked_in'
                                        ? 'Checked in'
                                        : null
                                "
                                badge-tone="green"
                                :meta="a.time"
                            />
                        </ul>
                    </template>
                </SectionCard>

                <!-- Nursing -->
                <SectionCard
                    v-if="sections.nursing"
                    title="Waiting at nursing points"
                    :icon="HeartPulse"
                    :count="sections.nursing.waiting_count"
                    :href="sections.nursing.href"
                    link-label="Open nursing"
                >
                    <ul
                        v-if="sections.nursing.worklist.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="row in sections.nursing.worklist"
                            :key="row.id"
                            :href="row.href"
                            :primary="row.patient"
                            :secondary="`${row.file_number} · ${row.service_point}`"
                            :badge="PRIORITY_LABELS[row.priority]"
                            :badge-tone="PRIORITY_TONES[row.priority]"
                            :meta="row.waited"
                            meta-sub="waiting"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No patients waiting at triage, ANC or immunization.
                    </p>
                </SectionCard>

                <!-- Appointments (front desk) -->
                <SectionCard
                    v-if="sections.appointments"
                    title="Today's appointments"
                    :icon="CalendarDays"
                    :count="sections.appointments.count"
                    :href="sections.appointments.href"
                    link-label="Open the book"
                >
                    <ul
                        v-if="sections.appointments.rows.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="a in sections.appointments.rows"
                            :key="a.id"
                            :href="a.href"
                            :primary="a.patient"
                            :secondary="`${a.file_number} · ${a.service_point}${a.provider ? ' · ' + a.provider : ''}`"
                            :badge="
                                a.status === 'checked_in' ? 'Checked in' : null
                            "
                            badge-tone="green"
                            :meta="a.time"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Nothing booked for today.
                    </p>
                </SectionCard>

                <!-- Laboratory -->
                <SectionCard
                    v-if="sections.laboratory"
                    title="Laboratory worklist"
                    :icon="FlaskConical"
                    :count="sections.laboratory.active_count"
                    :href="sections.laboratory.href"
                    link-label="Open laboratory"
                >
                    <div class="mb-3 grid grid-cols-3 gap-2">
                        <Link
                            v-for="c in sections.laboratory.counts"
                            :key="c.key"
                            :href="c.href"
                            class="rounded-lg border border-border px-3 py-2 transition-colors hover:bg-muted"
                        >
                            <p class="text-lg font-semibold tabular-nums">
                                {{ c.value }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ c.label }}
                            </p>
                        </Link>
                    </div>
                    <ul
                        v-if="sections.laboratory.worklist.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="o in sections.laboratory.worklist"
                            :key="o.id"
                            :href="o.href"
                            :primary="o.patient"
                            :secondary="`${o.accession_number} · ${o.status_label}`"
                            :badge="PRIORITY_LABELS[o.priority]"
                            :badge-tone="PRIORITY_TONES[o.priority]"
                            :meta="o.age"
                            meta-sub="since ordered"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No requisitions in progress.
                    </p>
                </SectionCard>

                <!-- Pharmacy -->
                <SectionCard
                    v-if="sections.pharmacy"
                    title="Prescriptions to dispense"
                    :icon="Pill"
                    :count="sections.pharmacy.waiting_count"
                    :href="sections.pharmacy.href"
                    link-label="Open pharmacy"
                    :sub="`${sections.pharmacy.dispensed_today} dispensed today`"
                >
                    <ul
                        v-if="sections.pharmacy.worklist.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="row in sections.pharmacy.worklist"
                            :key="row.id"
                            :href="row.href"
                            :primary="row.patient"
                            :secondary="row.file_number"
                            :badge="PRIORITY_LABELS[row.priority]"
                            :badge-tone="PRIORITY_TONES[row.priority]"
                            :meta="row.waited"
                            meta-sub="waiting"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No prescriptions waiting.
                    </p>
                </SectionCard>

                <!-- Admissions -->
                <SectionCard
                    v-if="sections.admissions"
                    title="Wards"
                    :icon="BedDouble"
                    :count="sections.admissions.admitted_now"
                    :href="sections.admissions.href"
                    link-label="Open admissions"
                    sub="Inpatients on the wards right now"
                >
                    <ul
                        v-if="sections.admissions.wards.length"
                        class="flex flex-col gap-2"
                    >
                        <li v-for="w in sections.admissions.wards" :key="w.id">
                            <Link
                                :href="w.href"
                                class="group block rounded-lg px-1 py-1 hover:bg-muted"
                            >
                                <div
                                    class="mb-1 flex items-center justify-between text-sm"
                                >
                                    <span class="font-medium">{{
                                        w.name
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ w.occupied }}/{{
                                            w.occupied + w.available
                                        }}
                                        occupied
                                        <template v-if="w.out_of_service">
                                            · {{ w.out_of_service }} out of
                                            service
                                        </template>
                                    </span>
                                </div>
                                <div
                                    class="flex h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <span
                                        :class="BED_BAR.occupied"
                                        :style="{
                                            width: pctOf(
                                                w.occupied,
                                                bedTotal(w),
                                            ),
                                        }"
                                    />
                                    <span
                                        :class="BED_BAR.available"
                                        :style="{
                                            width: pctOf(
                                                w.available,
                                                bedTotal(w),
                                            ),
                                        }"
                                    />
                                    <span
                                        :class="BED_BAR.out"
                                        :style="{
                                            width: pctOf(
                                                w.out_of_service,
                                                bedTotal(w),
                                            ),
                                        }"
                                    />
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        No wards have been set up yet.
                    </p>

                    <template v-if="sections.admissions.pending.length">
                        <h3
                            class="mt-4 mb-1 text-xs font-medium text-muted-foreground"
                        >
                            Awaiting a bed ({{
                                sections.admissions.pending_count
                            }})
                        </h3>
                        <ul class="divide-y divide-border/60">
                            <WorklistRow
                                v-for="p in sections.admissions.pending"
                                :key="p.id"
                                :href="p.href"
                                :primary="p.patient"
                                :secondary="`${p.file_number} · ${p.diagnosis}`"
                                :meta="p.age"
                                meta-sub="requested"
                            />
                        </ul>
                    </template>
                </SectionCard>

                <!-- Billing -->
                <SectionCard
                    v-if="sections.billing"
                    title="Unpaid bills"
                    :icon="ReceiptText"
                    :count="sections.billing.unpaid_count"
                    :href="sections.billing.href"
                    link-label="Open billing"
                >
                    <div class="mb-3 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-lg border border-border px-3 py-2">
                            <p class="text-lg font-semibold tabular-nums">
                                {{ naira(sections.billing.collected_today, 0) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Collected today
                            </p>
                        </div>
                        <div class="rounded-lg border border-border px-3 py-2">
                            <p class="text-lg font-semibold tabular-nums">
                                {{
                                    naira(
                                        sections.billing.my_collected_today,
                                        0,
                                    )
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Your till today
                            </p>
                        </div>
                        <div class="rounded-lg border border-border px-3 py-2">
                            <p
                                v-if="sections.billing.by_method.length"
                                class="flex flex-wrap gap-x-2 text-xs tabular-nums"
                            >
                                <span
                                    v-for="m in sections.billing.by_method"
                                    :key="m.label"
                                >
                                    <span class="text-muted-foreground">{{
                                        m.label
                                    }}</span>
                                    {{ naira(m.value, 0) }}
                                </span>
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                No payments yet
                            </p>
                            <p class="text-xs text-muted-foreground">
                                By method
                            </p>
                        </div>
                    </div>
                    <ul
                        v-if="sections.billing.unpaid.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="b in sections.billing.unpaid"
                            :key="b.id"
                            :href="b.href"
                            :primary="b.patient"
                            :secondary="`${b.file_number} · ${b.age} ago`"
                            :badge="
                                b.status === 'partially_paid'
                                    ? 'Part paid'
                                    : null
                            "
                            badge-tone="amber"
                            :meta="naira(b.balance, 0)"
                            :meta-sub="`of ${naira(b.total, 0)}`"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Every bill is settled.
                    </p>
                </SectionCard>

                <!-- Claims -->
                <SectionCard
                    v-if="sections.claims"
                    title="HMO and NHIA claims"
                    :icon="HandCoins"
                    :href="sections.claims.href"
                    link-label="Open claims"
                    :sub="`${sections.claims.draft_count} in draft · ${naira(sections.claims.receivable_amount, 0)} awaiting remittance`"
                >
                    <div
                        v-if="sections.claims.by_payer.length"
                        class="mb-3 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="p in sections.claims.by_payer"
                            :key="p.label"
                            class="rounded-md bg-muted px-2 py-1 text-xs tabular-nums"
                        >
                            <span class="text-muted-foreground">{{
                                p.label
                            }}</span>
                            {{ naira(p.value, 0) }}
                        </span>
                    </div>
                    <ul
                        v-if="sections.claims.awaiting.length"
                        class="divide-y divide-border/60"
                    >
                        <WorklistRow
                            v-for="c in sections.claims.awaiting"
                            :key="c.id"
                            :href="c.href"
                            :primary="c.patient"
                            :secondary="`${c.claim_number} · ${c.payer}`"
                            :meta="naira(c.amount, 0)"
                            :meta-sub="`${c.age} ago`"
                        />
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Nothing awaiting remittance.
                    </p>
                </SectionCard>

                <p
                    v-if="!hasWorkPanels"
                    class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    Your roles have no worklist on the home screen. Use the
                    modules in the sidebar.
                </p>
            </div>

            <!-- Right rail -->
            <div class="flex flex-col gap-4">
                <!-- Alerts -->
                <SectionCard title="Needs attention" :icon="AlertTriangle">
                    <ul
                        v-if="home.alerts.length"
                        class="flex flex-col divide-y divide-border/60"
                    >
                        <li v-for="alert in home.alerts" :key="alert.key">
                            <Link
                                :href="alert.href"
                                class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-muted"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-semibold tabular-nums"
                                    :class="ALERT_TONES[alert.tone]"
                                >
                                    {{ alert.count }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">
                                        {{ alert.label }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ alert.sub }}
                                    </p>
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground"
                    >
                        <CheckCircle2
                            class="size-4 text-emerald-600 dark:text-emerald-400"
                        />
                        Nothing needs your attention.
                    </p>
                </SectionCard>

                <!-- Queues -->
                <SectionCard
                    v-if="sections.queues"
                    title="Service queues"
                    :icon="ListChecks"
                    :href="sections.queues.href"
                    link-label="All queues"
                >
                    <ul
                        v-if="sections.queues.points.length"
                        class="flex flex-col divide-y divide-border/60"
                    >
                        <li v-for="p in sections.queues.points" :key="p.slug">
                            <Link
                                :href="p.href"
                                class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2 text-sm hover:bg-muted"
                            >
                                <span class="truncate">{{ p.name }}</span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                >
                                    <span
                                        class="font-semibold"
                                        :class="
                                            p.waiting ? 'text-foreground' : ''
                                        "
                                        >{{ p.waiting }}</span
                                    >
                                    waiting
                                    <template v-if="p.in_service">
                                        · {{ p.in_service }} in
                                        service</template
                                    >
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        No service points are active.
                    </p>
                </SectionCard>

                <!-- Management -->
                <SectionCard
                    v-if="sections.management"
                    :title="sections.management.period"
                    :icon="ChartColumn"
                    :href="sections.management.overview_href"
                    link-label="Executive overview"
                    sub="Month to date"
                >
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Visits
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    sections.management.visits.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                New patients
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    sections.management.new_patients.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Consultations
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    sections.management.consultations.toLocaleString()
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Revenue
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ naira(sections.management.revenue, 0) }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Outstanding balances
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{ naira(sections.management.outstanding, 0) }}
                            </dd>
                        </div>
                    </dl>
                    <Link
                        :href="sections.management.reports_href"
                        class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                    >
                        <Banknote class="size-3.5" />
                        Report catalogue
                    </Link>
                </SectionCard>
            </div>
        </div>
    </div>
</template>
