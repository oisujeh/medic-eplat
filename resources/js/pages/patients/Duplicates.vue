<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

/**
 * The duplicate review queue: pairs of records the matcher scored as
 * probably one person, highest score first.
 */
type Summary = {
    id: number;
    file_number: string;
    name: string;
    sex: string;
    age: number | null;
    dob: string | null;
    phone: string | null;
    lga: string | null;
    registered_at: string | null;
    url: string;
};

type Row = {
    id: number;
    score: number;
    level: 'auto' | 'review';
    level_label: string;
    reasons: string[];
    flagged_at: string | null;
    a: Summary;
    b: Summary;
    url: string;
};

type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    candidates: Paginated<Row>;
    canMerge: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Patients', href: '/patients' },
            { title: 'Possible duplicates', href: '/patients/duplicates' },
        ],
    },
});

const describe = (p: Summary) =>
    [
        p.age !== null ? `${p.age}y ${p.sex}` : p.sex,
        p.dob ? `born ${p.dob}` : null,
        p.phone,
        p.lga,
    ]
        .filter(Boolean)
        .join(' · ');
</script>

<template>
    <Head title="Possible duplicates" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Link
            href="/patients"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to patients
        </Link>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Possible duplicate records
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                Pairs of records that may be the same person. Open a pair to
                compare them side by side, then merge them or mark them as
                different people.
                {{
                    props.canMerge ? '' : 'Merging needs the merge permission.'
                }}
            </p>
        </div>

        <div
            v-if="!props.candidates.data.length"
            class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
        >
            <Users class="mx-auto mb-2 size-6" />
            Nothing waiting for review.
        </div>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="row in props.candidates.data"
                :key="row.id"
                class="rounded-xl border border-border bg-card p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                row.level === 'auto'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                        >
                            {{ row.level_label }}
                        </Badge>
                        <span class="font-mono text-xs text-muted-foreground"
                            >score {{ row.score }}</span
                        >
                        <span
                            v-if="row.flagged_at"
                            class="text-xs text-muted-foreground"
                            >· flagged {{ row.flagged_at }}</span
                        >
                    </div>
                    <Link
                        :href="row.url"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        Compare and decide →
                    </Link>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="p in [row.a, row.b]"
                        :key="p.id"
                        class="rounded-lg bg-muted/40 p-3"
                    >
                        <div class="font-medium">{{ p.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            <span class="font-mono">{{ p.file_number }}</span>
                            · {{ describe(p) }}
                        </div>
                        <div
                            v-if="p.registered_at"
                            class="text-xs text-muted-foreground/70"
                        >
                            Registered {{ p.registered_at }}
                        </div>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    {{ row.reasons.join(' · ') }}
                </p>
            </li>
        </ul>

        <nav
            v-if="props.candidates.links.length > 3"
            class="flex flex-wrap gap-1"
            aria-label="Pagination"
        >
            <template v-for="link in props.candidates.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                    v-html="link.label"
                />
                <span
                    v-else
                    class="rounded-md px-3 py-1.5 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
