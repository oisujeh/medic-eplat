<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Baby,
    BedDouble,
    ClipboardList,
    ExternalLink,
    FlaskConical,
    HeartOff,
    LogOut,
    Package,
    Pill,
    Radar,
    Send,
    Stethoscope,
    Syringe,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { TimelineEntry, TimelineKindOption } from '@/types/clinical';

/**
 * The patient's history as one dated feed. Filters are applied on the
 * client over the entries delivered, so toggling a kind is instant.
 */
const props = withDefaults(
    defineProps<{
        entries: TimelineEntry[];
        kinds: TimelineKindOption[];
        /** Compact rows without grouping, for a sidebar. */
        compact?: boolean;
        emptyText?: string;
    }>(),
    { compact: false, emptyText: 'Nothing on record yet.' },
);

const icons: Record<string, unknown> = {
    UserPlus,
    Stethoscope,
    ClipboardList,
    Activity,
    FlaskConical,
    Pill,
    Package,
    Syringe,
    BedDouble,
    LogOut,
    Baby,
    Send,
    Radar,
    HeartOff,
};

const hidden = ref<Set<string>>(new Set());

function toggle(kind: string) {
    const next = new Set(hidden.value);

    if (next.has(kind)) {
        next.delete(kind);
    } else {
        next.add(kind);
    }

    hidden.value = next;
}

function only(kind: string) {
    hidden.value = new Set(
        props.kinds.map((k) => k.value).filter((k) => k !== kind),
    );
}

function showAll() {
    hidden.value = new Set();
}

const visible = computed(() =>
    props.entries.filter((e) => !hidden.value.has(e.kind)),
);

const groups = computed(() => {
    const byDate = new Map<string, { label: string; entries: TimelineEntry[] }>();

    for (const e of visible.value) {
        const group = byDate.get(e.date) ?? { label: e.date_label, entries: [] };
        group.entries.push(e);
        byDate.set(e.date, group);
    }

    return [...byDate.values()];
});

const toneClass = (tone: string) =>
    ({
        red: 'bg-red-500/10 text-red-700 dark:text-red-400',
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        blue: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
        violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
    })[tone] ?? 'bg-muted text-muted-foreground';

const iconClass = (tone: string) =>
    ({
        red: 'border-red-500/40 text-red-600',
        amber: 'border-amber-500/40 text-amber-600',
        green: 'border-emerald-500/40 text-emerald-600',
        blue: 'border-sky-500/40 text-sky-600',
        violet: 'border-violet-500/40 text-violet-600',
    })[tone] ?? 'border-border text-muted-foreground';
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Kind filters -->
        <div
            v-if="!compact && props.kinds.length > 1"
            class="flex flex-wrap items-center gap-1.5"
        >
            <button
                v-for="k in props.kinds"
                :key="k.value"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                :class="
                    hidden.has(k.value)
                        ? 'border-border text-muted-foreground/60 line-through'
                        : 'border-primary/30 bg-primary/5 text-foreground'
                "
                :aria-pressed="!hidden.has(k.value)"
                @click="toggle(k.value)"
                @dblclick.prevent="only(k.value)"
            >
                <component :is="icons[k.icon]" class="size-3.5" />
                {{ k.label }}
                <span class="tabular-nums text-muted-foreground">{{ k.count }}</span>
            </button>
            <button
                v-if="hidden.size"
                type="button"
                class="ml-1 text-xs text-primary hover:underline"
                @click="showAll"
            >
                Show all
            </button>
        </div>

        <p v-if="!visible.length" class="text-sm text-muted-foreground">
            {{ props.emptyText }}
        </p>

        <!-- Compact list for sidebars -->
        <ol v-else-if="compact" class="flex flex-col divide-y divide-border">
            <li
                v-for="e in visible"
                :key="e.key"
                class="flex items-start gap-2.5 py-2 first:pt-0 last:pb-0"
            >
                <span
                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border bg-card"
                    :class="iconClass(e.tone)"
                >
                    <component :is="icons[e.icon]" class="size-3" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-2">
                        <component
                            :is="e.href ? Link : 'span'"
                            :href="e.href ?? undefined"
                            class="truncate text-sm font-medium"
                            :class="e.href ? 'hover:underline' : ''"
                        >
                            {{ e.title }}
                        </component>
                        <span class="shrink-0 text-[11px] text-muted-foreground">
                            {{ e.date }}
                        </span>
                    </div>
                    <p
                        v-if="e.detail"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ e.detail }}
                    </p>
                </div>
            </li>
        </ol>

        <!-- Full timeline grouped by day -->
        <div v-else class="flex flex-col gap-6">
            <section v-for="g in groups" :key="g.label">
                <h3
                    class="sticky top-0 z-10 mb-2 bg-background/95 py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase backdrop-blur"
                >
                    {{ g.label }}
                </h3>
                <ol class="relative ml-3 flex flex-col gap-3 border-l border-border pl-5">
                    <li v-for="e in g.entries" :key="e.key" class="relative">
                        <span
                            class="absolute -left-[1.85rem] top-0.5 flex size-6 items-center justify-center rounded-full border bg-card"
                            :class="iconClass(e.tone)"
                        >
                            <component :is="icons[e.icon]" class="size-3.5" />
                        </span>
                        <div class="rounded-lg border border-border bg-card px-4 py-3">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-mono text-xs text-muted-foreground">{{ e.time }}</span>
                                <span class="text-xs text-muted-foreground">{{ e.kind_label }}</span>
                                <span
                                    v-if="e.badge"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="toneClass(e.tone)"
                                    >{{ e.badge }}</span
                                >
                                <Link
                                    v-if="e.href"
                                    :href="e.href"
                                    class="ml-auto inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                >
                                    Open <ExternalLink class="size-3" />
                                </Link>
                            </div>
                            <p class="mt-1 text-sm font-medium">{{ e.title }}</p>
                            <p
                                v-if="e.detail"
                                class="mt-0.5 text-sm whitespace-pre-line text-muted-foreground"
                            >
                                {{ e.detail }}
                            </p>
                            <p v-if="e.by" class="mt-1 text-xs text-muted-foreground/80">
                                {{ e.by }}
                            </p>
                        </div>
                    </li>
                </ol>
            </section>
        </div>
    </div>
</template>
