<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        icon?: Component | null;
        /** A count shown beside the title, e.g. how many are waiting. */
        count?: number | null;
        href?: string | null;
        linkLabel?: string;
        sub?: string | null;
    }>(),
    { icon: null, count: null, href: null, linkLabel: 'View all', sub: null },
);
</script>

<template>
    <section class="rounded-xl border border-border bg-card">
        <header
            class="flex items-center justify-between gap-3 border-b border-border px-5 py-3"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    v-if="icon"
                    class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
                >
                    <component :is="icon" class="size-4" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold">
                        {{ title }}
                        <span
                            v-if="count !== null"
                            class="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums"
                            >{{ count }}</span
                        >
                    </h2>
                    <p
                        v-if="sub"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ sub }}
                    </p>
                </div>
            </div>
            <Link
                v-if="href"
                :href="href"
                class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
            >
                {{ linkLabel }}
                <ArrowRight class="size-3.5" />
            </Link>
        </header>
        <div class="px-5 py-3">
            <slot />
        </div>
    </section>
</template>
