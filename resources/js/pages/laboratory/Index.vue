<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FlaskConical, Search } from '@lucide/vue';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Order = {
    id: number;
    accession_number: string;
    status: string;
    status_label: string;
    tone: string;
    priority: string;
    priority_label: string;
    test_count: number;
    resulted_count: number;
    departments: string[];
    ordered_ago: string | null;
    patient: {
        name: string;
        initials: string;
        file_number: string;
        sex: string;
        age: number | null;
    };
    url: string;
};

const props = defineProps<{
    orders: Order[];
    filters: { status: string; department: string; q: string };
    counts: {
        active: number;
        ordered: number;
        in_progress: number;
        completed: number;
    };
    departments: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Laboratory', href: '/laboratory' }],
    },
});

const search = ref(props.filters.q ?? '');

const tabs = [
    { key: 'active', label: 'Active', count: () => props.counts.active },
    {
        key: 'ordered',
        label: 'Awaiting collection',
        count: () => props.counts.ordered,
    },
    {
        key: 'in_progress',
        label: 'In progress',
        count: () => props.counts.in_progress,
    },
    {
        key: 'completed',
        label: 'Completed',
        count: () => props.counts.completed,
    },
];

function navigate(patch: Record<string, string>) {
    router.get(
        '/laboratory',
        { ...props.filters, ...patch },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        green: 'bg-green-500/10 text-green-700 dark:text-green-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

function priorityClass(priority: string): string {
    if (priority === 'emergency') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400';
    }

    if (priority === 'urgent') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Laboratory" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Laboratory</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Specimen processing and result verification worklist.
            </p>
        </div>

        <!-- Filters -->
        <div class="flex flex-col gap-3">
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
                        class="rounded-full bg-muted px-1.5 text-[11px] text-muted-foreground"
                        >{{ tab.count() }}</span
                    >
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 sm:max-w-xs">
                    <Search
                        class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Accession or patient…"
                        class="pl-8"
                        @keyup.enter="navigate({ q: search })"
                    />
                </div>
                <Select
                    :model-value="filters.department || 'all'"
                    @update:model-value="
                        (v) =>
                            navigate({
                                department: v === 'all' ? '' : String(v),
                            })
                    "
                >
                    <SelectTrigger class="w-52">
                        <SelectValue placeholder="All departments" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All departments</SelectItem>
                        <SelectItem
                            v-for="d in departments"
                            :key="d.value"
                            :value="d.value"
                            >{{ d.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Worklist -->
        <div class="flex flex-col gap-3">
            <div
                v-if="!orders.length"
                class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
            >
                No requisitions match this view.
            </div>

            <Link
                v-for="order in orders"
                :key="order.id"
                :href="order.url"
                class="rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-muted/30"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                        >
                            {{ order.patient.initials }}
                        </span>
                        <div>
                            <p class="font-medium">{{ order.patient.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                <span class="font-mono">{{
                                    order.accession_number
                                }}</span>
                                · {{ order.patient.file_number }} ·
                                {{ order.patient.sex
                                }}{{
                                    order.patient.age !== null
                                        ? ' · ' + order.patient.age + 'y'
                                        : ''
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="order.priority !== 'normal'"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="priorityClass(order.priority)"
                            >{{ order.priority_label }}</span
                        >
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="toneClass(order.tone)"
                            >{{ order.status_label }}</span
                        >
                    </div>
                </div>

                <div
                    class="mt-3 flex flex-wrap items-center justify-between gap-2"
                >
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            v-for="d in order.departments"
                            :key="d"
                            class="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground capitalize"
                            >{{ d }}</span
                        >
                    </div>
                    <span class="text-xs text-muted-foreground">
                        {{ order.resulted_count }}/{{
                            order.test_count
                        }}
                        resulted
                        <span v-if="order.ordered_ago"
                            >· ordered {{ order.ordered_ago }}</span
                        >
                    </span>
                </div>
            </Link>
        </div>

        <p
            v-if="orders.length"
            class="flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            <FlaskConical class="size-3.5" />
            Showing up to 60 requisitions.
        </p>
    </div>
</template>
