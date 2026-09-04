<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import * as LucideIcons from '@lucide/vue';
import {
    ArrowRight,
    FileText,
    LayoutGrid,
    Search,
    Star,
    type LucideIcon,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';

type Report = {
    key: string;
    name: string;
    category: string;
    description: string;
    icon: string;
    type: 'table' | 'dashboard';
    featured: boolean;
    url: string;
};
type Category = {
    key: string;
    name: string;
    icon: string;
    description: string;
    count: number;
};

const props = defineProps<{
    categories: Category[];
    reports: Report[];
    featured: Report[];
    filters: { range: string; from: string; to: string; label: string };
    presets: Array<{ key: string; label: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: '/reports' }] },
});

const icons = LucideIcons as unknown as Record<string, LucideIcon>;
const resolveIcon = (name: string | null): LucideIcon =>
    (name && icons[name]) || FileText;

const search = ref('');
const activeCategory = ref<string>('all');

const categoryName = computed<Record<string, string>>(() =>
    Object.fromEntries(props.categories.map((c) => [c.key, c.name])),
);

const totalCount = computed(() => props.reports.length);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.reports.filter((r) => {
        const inCategory = activeCategory.value === 'all' || r.category === activeCategory.value;
        const matches =
            !q ||
            r.name.toLowerCase().includes(q) ||
            r.description.toLowerCase().includes(q);
        return inCategory && matches;
    });
});

const showFeatured = computed(
    () => activeCategory.value === 'all' && !search.value.trim() && props.featured.length > 0,
);

function applyPreset(key: string) {
    router.get('/reports', { range: key }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <!-- Header -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Reports</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Browse, run and export reports across the facility.
                </p>
            </div>
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
        </div>

        <!-- Search -->
        <div class="relative max-w-xl">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input v-model="search" placeholder="Search reports…" class="pl-9" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
            <!-- Category rail -->
            <aside class="lg:sticky lg:top-4 lg:self-start">
                <div class="rounded-xl border border-border bg-card p-2">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors"
                        :class="
                            activeCategory === 'all'
                                ? 'bg-primary/10 font-medium text-primary'
                                : 'text-muted-foreground hover:bg-muted'
                        "
                        @click="activeCategory = 'all'"
                    >
                        <LayoutGrid class="size-4" />
                        <span class="flex-1 text-left">All reports</span>
                        <span class="text-xs tabular-nums">{{ totalCount }}</span>
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c.key"
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors"
                        :class="
                            activeCategory === c.key
                                ? 'bg-primary/10 font-medium text-primary'
                                : 'text-muted-foreground hover:bg-muted'
                        "
                        @click="activeCategory = c.key"
                    >
                        <component :is="resolveIcon(c.icon)" class="size-4" />
                        <span class="flex-1 truncate text-left">{{ c.name }}</span>
                        <span class="text-xs tabular-nums">{{ c.count }}</span>
                    </button>
                </div>
            </aside>

            <!-- Reports -->
            <div class="flex flex-col gap-6">
                <!-- Featured -->
                <section v-if="showFeatured">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <Star class="size-4 text-amber-500" /> Featured reports
                    </h2>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <Link
                            v-for="r in featured"
                            :key="r.key"
                            :href="r.url"
                            class="group flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40"
                        >
                            <div class="flex items-start justify-between">
                                <span
                                    class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <component :is="resolveIcon(r.icon)" class="size-5" />
                                </span>
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                                    >{{ categoryName[r.category] }}</span
                                >
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">{{ r.name }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ r.description }}
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                            >
                                {{ r.type === 'dashboard' ? 'Open' : 'Generate' }}
                                <ArrowRight
                                    class="size-3.5 transition-transform group-hover:translate-x-0.5"
                                />
                            </span>
                        </Link>
                    </div>
                </section>

                <!-- All / filtered -->
                <section>
                    <h2 class="mb-3 text-sm font-semibold">
                        {{
                            activeCategory === 'all'
                                ? 'All reports'
                                : categoryName[activeCategory]
                        }}
                        <span class="text-muted-foreground">· {{ filtered.length }}</span>
                    </h2>

                    <div
                        v-if="filtered.length"
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <Link
                            v-for="r in filtered"
                            :key="r.key"
                            :href="r.url"
                            class="group flex items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary"
                            >
                                <component :is="resolveIcon(r.icon)" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium">{{ r.name }}</p>
                                    <Star
                                        v-if="r.featured"
                                        class="size-3.5 shrink-0 text-amber-500"
                                    />
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                    {{ r.description }}
                                </p>
                                <span
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary"
                                >
                                    {{ r.type === 'dashboard' ? 'Open' : 'Generate' }}
                                    <ArrowRight
                                        class="size-3 transition-transform group-hover:translate-x-0.5"
                                    />
                                </span>
                            </div>
                        </Link>
                    </div>
                    <div
                        v-else
                        class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
                    >
                        No reports match your search.
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
