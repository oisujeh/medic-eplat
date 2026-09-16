<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CatalogueTest = {
    id: number;
    code: string;
    name: string;
    department: string;
    department_label: string;
    unit: string | null;
    is_panel: boolean;
    reference: string | null;
    bands: number;
    url: string;
};

const props = defineProps<{
    tests: CatalogueTest[];
    filters: { q: string };
    departments: Array<{ value: string; label: string }>;
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Laboratory', href: '/laboratory' },
            { title: 'Test catalogue', href: '/laboratory/catalogue' },
        ],
    },
});

const q = ref(props.filters.q);

function search() {
    router.get(
        '/laboratory/catalogue',
        { q: q.value },
        { preserveState: true, replace: true },
    );
}

// Tests grouped under their department, in catalogue order.
const groups = computed(() =>
    props.departments
        .map((d) => ({
            ...d,
            tests: props.tests.filter((t) => t.department === d.value),
        }))
        .filter((g) => g.tests.length > 0),
);
</script>

<template>
    <Head title="Test catalogue" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link href="/laboratory" aria-label="Back to laboratory">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                    >
                        <BookOpen class="size-5 text-muted-foreground" />
                        Test catalogue
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Every orderable test with its reference limits. Open a
                        test to set limits by age band and sex.
                    </p>
                </div>
            </div>
            <form class="relative w-full sm:w-72" @submit.prevent="search">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="pl-9"
                    placeholder="Search by code or name"
                    @change="search"
                />
            </form>
        </div>

        <section
            v-for="group in groups"
            :key="group.value"
            class="overflow-x-auto rounded-xl border border-border bg-card"
        >
            <h2
                class="border-b border-border px-4 py-2.5 text-sm font-semibold"
            >
                {{ group.label }}
            </h2>
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2 font-medium">Code</th>
                        <th class="px-4 py-2 font-medium">Test</th>
                        <th class="px-4 py-2 font-medium">Unit</th>
                        <th class="px-4 py-2 font-medium">Reference</th>
                        <th class="px-4 py-2 font-medium">Bands</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-for="t in group.tests" :key="t.id">
                        <td class="px-4 py-2 font-mono text-xs">
                            {{ t.code }}
                        </td>
                        <td class="px-4 py-2">
                            <Link
                                :href="t.url"
                                class="font-medium hover:underline"
                                >{{ t.name }}</Link
                            >
                            <span
                                v-if="t.is_panel"
                                class="ml-2 rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                                >panel</span
                            >
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ t.unit ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ t.reference ?? '—' }}
                        </td>
                        <td class="px-4 py-2 tabular-nums">
                            <span v-if="t.bands" class="font-medium">{{
                                t.bands
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >single</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <p v-if="!groups.length" class="text-sm text-muted-foreground">
            No tests match that search.
        </p>
    </div>
</template>
