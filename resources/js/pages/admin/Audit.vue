<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, ShieldCheck, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    index as auditIndex,
    verify as auditVerify,
} from '@/routes/admin/audit';

type Change = { field: string; old: string | null; new: string | null };

type Entry = {
    id: number;
    occurred_at: string;
    user: string;
    user_id: number | null;
    action: string;
    label: string | null;
    type: string | null;
    route: string | null;
    ip_address: string | null;
    user_agent: string | null;
    patient: {
        id: number;
        file_number: string;
        name: string;
        url: string;
    } | null;
    changes: Change[];
    hash: string;
};

type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Filters = {
    search: string;
    action: string;
    user: number | null;
    patient: string;
    type: string;
    from: string;
    to: string;
};

const props = defineProps<{
    entries: Paginated<Entry>;
    filters: Filters;
    actions: string[];
    users: Array<{ id: number; name: string }>;
    types: Array<{ value: string; label: string }>;
    summary: { total: number; first_at: string | null; last_at: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin/users' },
            { title: 'Audit trail', href: '/admin/audit' },
        ],
    },
});

const ALL = 'all';

const search = ref(props.filters.search ?? '');
const patient = ref(props.filters.patient ?? '');
const action = ref(props.filters.action || ALL);
const user = ref(props.filters.user ? String(props.filters.user) : ALL);
const type = ref(props.filters.type || ALL);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function apply() {
    router.get(
        auditIndex().url,
        {
            search: search.value || undefined,
            patient: patient.value || undefined,
            action: action.value === ALL ? undefined : action.value,
            user: user.value === ALL ? undefined : user.value,
            type: type.value === ALL ? undefined : type.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watchDebounced([search, patient], apply, { debounce: 300 });
watch([action, user, type, from, to], apply);

const hasFilters = computed(
    () =>
        search.value !== '' ||
        patient.value !== '' ||
        action.value !== ALL ||
        user.value !== ALL ||
        type.value !== ALL ||
        from.value !== '' ||
        to.value !== '',
);

function clearFilters() {
    search.value = '';
    patient.value = '';
    action.value = ALL;
    user.value = ALL;
    type.value = ALL;
    from.value = '';
    to.value = '';
}

const expanded = ref<Set<number>>(new Set());

function toggle(id: number) {
    const next = new Set(expanded.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expanded.value = next;
}

const verifying = ref(false);

function verify() {
    router.post(
        auditVerify().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (verifying.value = true),
            onFinish: () => (verifying.value = false),
        },
    );
}

const actionLabels: Record<string, string> = {
    viewed: 'Viewed',
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    exported: 'Exported',
    login: 'Signed in',
    logout: 'Signed out',
    login_failed: 'Sign-in failed',
};

function actionLabel(value: string): string {
    return actionLabels[value] ?? value;
}

function actionVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (value) {
        case 'deleted':
        case 'login_failed':
            return 'destructive';
        case 'created':
        case 'updated':
            return 'default';
        case 'viewed':
        case 'exported':
            return 'outline';
        default:
            return 'secondary';
    }
}

/** Anything worth expanding: a diff, or request context. */
function hasDetails(entry: Entry): boolean {
    return (
        entry.changes.length > 0 ||
        entry.ip_address !== null ||
        entry.route !== null
    );
}

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
    <Head title="Audit trail" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Audit trail
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Who opened, created, changed or removed which record, and
                    when. Entries are append-only and hash-chained; verifying
                    the chain confirms nothing has been altered outside the
                    application.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="verifying"
                @click="verify"
            >
                <ShieldCheck class="size-4" />
                {{ verifying ? 'Verifying…' : 'Verify integrity' }}
            </Button>
        </div>

        <AdminNav current="audit" />

        <div
            class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div class="grid gap-1.5">
                <Label class="text-xs">Search</Label>
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Record, staff name, IP or route"
                />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Patient</Label>
                <Input
                    v-model="patient"
                    type="search"
                    placeholder="File number or name"
                />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Action</Label>
                <Select v-model="action">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All actions" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All actions</SelectItem>
                        <SelectItem v-for="a in actions" :key="a" :value="a">
                            {{ actionLabel(a) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Staff</Label>
                <Select v-model="user">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All staff" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All staff</SelectItem>
                        <SelectItem
                            v-for="u in users"
                            :key="u.id"
                            :value="String(u.id)"
                        >
                            {{ u.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Record type</Label>
                <Select v-model="type">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="All record types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All record types</SelectItem>
                        <SelectItem
                            v-for="t in types"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </SelectItem>
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
                        <th class="w-8 px-2 py-2.5"></th>
                        <th class="px-4 py-2.5 font-medium">When</th>
                        <th class="px-4 py-2.5 font-medium">Staff</th>
                        <th class="px-4 py-2.5 font-medium">Action</th>
                        <th class="px-4 py-2.5 font-medium">Record</th>
                        <th class="px-4 py-2.5 font-medium">Patient</th>
                        <th class="px-4 py-2.5 font-medium">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <template v-for="e in props.entries.data" :key="e.id">
                        <tr
                            :class="hasDetails(e) ? 'cursor-pointer' : ''"
                            @click="hasDetails(e) && toggle(e.id)"
                        >
                            <td class="px-2 py-2.5 text-muted-foreground">
                                <component
                                    :is="
                                        expanded.has(e.id)
                                            ? ChevronDown
                                            : ChevronRight
                                    "
                                    v-if="hasDetails(e)"
                                    class="size-4"
                                />
                            </td>
                            <td
                                class="px-4 py-2.5 whitespace-nowrap text-muted-foreground"
                            >
                                {{ e.occurred_at }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium">{{ e.user }}</div>
                                <div
                                    v-if="e.ip_address"
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ e.ip_address }}
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <Badge :variant="actionVariant(e.action)">
                                    {{ actionLabel(e.action) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2.5">
                                <div>{{ e.label ?? '—' }}</div>
                                <div
                                    v-if="e.type"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ e.type }}
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <template v-if="e.patient">
                                    <Link
                                        :href="e.patient.url"
                                        class="font-medium hover:underline"
                                        @click.stop
                                    >
                                        {{ e.patient.name }}
                                    </Link>
                                    <div
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ e.patient.file_number }}
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-2.5 text-muted-foreground">
                                <span v-if="e.changes.length">
                                    {{ e.changes.length }}
                                    {{
                                        e.changes.length === 1
                                            ? 'field'
                                            : 'fields'
                                    }}
                                </span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                        <tr v-if="expanded.has(e.id)" class="bg-muted/30">
                            <td></td>
                            <td colspan="6" class="px-4 py-3">
                                <table
                                    v-if="e.changes.length"
                                    class="w-full text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="text-left text-muted-foreground"
                                        >
                                            <th class="py-1 pr-4 font-medium">
                                                Field
                                            </th>
                                            <th class="py-1 pr-4 font-medium">
                                                Before
                                            </th>
                                            <th class="py-1 font-medium">
                                                After
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr
                                            v-for="c in e.changes"
                                            :key="c.field"
                                            class="align-top"
                                        >
                                            <td
                                                class="py-1 pr-4 font-mono whitespace-nowrap"
                                            >
                                                {{ c.field }}
                                            </td>
                                            <td
                                                class="max-w-xs py-1 pr-4 break-words whitespace-pre-wrap text-muted-foreground"
                                            >
                                                {{ c.old ?? '—' }}
                                            </td>
                                            <td
                                                class="max-w-xs py-1 break-words whitespace-pre-wrap"
                                            >
                                                {{ c.new ?? '—' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <dl
                                    class="mt-2 grid gap-x-6 gap-y-1 text-xs text-muted-foreground sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <div v-if="e.route">
                                        <dt class="font-medium">Screen</dt>
                                        <dd class="font-mono">{{ e.route }}</dd>
                                    </div>
                                    <div v-if="e.user_agent">
                                        <dt class="font-medium">Browser</dt>
                                        <dd
                                            class="truncate"
                                            :title="e.user_agent"
                                        >
                                            {{ e.user_agent }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium">Entry hash</dt>
                                        <dd class="font-mono">{{ e.hash }}…</dd>
                                    </div>
                                </dl>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!props.entries.data.length">
                        <td
                            colspan="7"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            {{
                                hasFilters
                                    ? 'No entries match these filters.'
                                    : 'Nothing has been recorded yet.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                Showing {{ props.entries.from ?? 0 }}–{{
                    props.entries.to ?? 0
                }}
                of {{ props.entries.total }} matching entries
                <span v-if="summary.total !== props.entries.total">
                    ({{ summary.total }} in total)
                </span>
            </p>
            <div
                v-if="props.entries.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <template v-for="(link, i) in props.entries.links" :key="i">
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
