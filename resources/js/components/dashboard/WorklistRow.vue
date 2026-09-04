<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

withDefaults(
    defineProps<{
        href: string;
        /** Main line, usually the patient's name. */
        primary: string;
        /** Muted line under it: file number, service point, diagnosis. */
        secondary?: string | null;
        /** Right-aligned figure: waited time, an amount, a clock time. */
        meta?: string | null;
        metaSub?: string | null;
        /** A small badge between the text and the meta, e.g. a priority. */
        badge?: string | null;
        badgeTone?: 'red' | 'amber' | 'blue' | 'violet' | 'green' | 'muted';
    }>(),
    {
        secondary: null,
        meta: null,
        metaSub: null,
        badge: null,
        badgeTone: 'muted',
    },
);

const TONES: Record<string, string> = {
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
    violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    muted: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <li>
        <Link
            :href="href"
            class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-muted"
        >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ primary }}</p>
                <p
                    v-if="secondary"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ secondary }}
                </p>
            </div>
            <span
                v-if="badge"
                class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                :class="TONES[badgeTone]"
                >{{ badge }}</span
            >
            <div v-if="meta" class="shrink-0 text-right">
                <p class="text-sm font-medium tabular-nums">{{ meta }}</p>
                <p v-if="metaSub" class="text-xs text-muted-foreground">
                    {{ metaSub }}
                </p>
            </div>
            <ChevronRight
                class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
            />
        </Link>
    </li>
</template>
