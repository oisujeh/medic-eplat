<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, UserPlus } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type PatientRow = {
    id: number;
    file_number: string;
    name: string;
    initials: string;
    sex: string;
    age: number | null;
    phone: string | null;
    state: string | null;
    lga: string | null;
    coverage: string;
    visit_category: string;
    registered_at: string | null;
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
    patients: Paginated<PatientRow>;
    filters: { search: string };
    canRegister: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Patients', href: '/patients' }],
    },
});

const search = ref(props.filters.search ?? '');

watchDebounced(
    search,
    (value) => {
        router.get(
            '/patients',
            { search: value || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    },
    { debounce: 300 },
);
</script>

<template>
    <Head title="Patients" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Patients</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.patients.total }}
                    registered patient{{ props.patients.total === 1 ? '' : 's' }}
                </p>
            </div>
            <Button v-if="props.canRegister" as-child>
                <Link href="/registration">
                    <UserPlus class="size-4" />
                    Register patient
                </Link>
            </Button>
        </div>

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Search name, file number or phone"
                class="pl-9"
            />
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground uppercase"
                        >
                            <th class="px-4 py-3 font-medium">Patient</th>
                            <th class="px-4 py-3 font-medium">Sex / Age</th>
                            <th class="px-4 py-3 font-medium">Phone</th>
                            <th class="px-4 py-3 font-medium">Residence</th>
                            <th class="px-4 py-3 font-medium">Coverage</th>
                            <th class="px-4 py-3 font-medium">Visit</th>
                            <th class="px-4 py-3 font-medium">Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in props.patients.data"
                            :key="p.id"
                            class="cursor-pointer border-b border-border/60 transition-colors last:border-0 hover:bg-muted/50"
                            @click="router.visit(p.url)"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                    >
                                        {{ p.initials }}
                                    </span>
                                    <div class="flex flex-col">
                                        <Link
                                            :href="p.url"
                                            class="font-medium hover:underline"
                                            @click.stop
                                        >
                                            {{ p.name }}
                                        </Link>
                                        <span
                                            class="font-mono text-xs text-muted-foreground"
                                        >
                                            {{ p.file_number }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ p.sex }}{{ p.age !== null ? ' · ' + p.age + 'y' : '' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ p.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="p.state">{{ p.lga }}, {{ p.state }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="
                                        p.coverage === 'hmo'
                                            ? 'bg-teal-500/10 text-teal-700 dark:text-teal-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ p.coverage === 'hmo' ? 'HMO' : 'Private' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ p.visit_category }}
                            </td>
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ p.registered_at }}
                            </td>
                        </tr>

                        <tr v-if="!props.patients.data.length">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                            >
                                No patients found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="props.patients.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                Showing {{ props.patients.from ?? 0 }}–{{ props.patients.to ?? 0 }}
                of {{ props.patients.total }}
            </p>
            <div class="flex flex-wrap gap-1">
                <template v-for="(link, i) in props.patients.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border hover:bg-muted'
                        "
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border px-2 text-sm text-muted-foreground/50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
