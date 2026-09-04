<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import * as LucideIcons from '@lucide/vue';
import { ListChecks, Lock, SlidersHorizontal } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type ServicePoint = {
    name: string;
    slug: string;
    icon: string | null;
    description: string | null;
    waiting: number;
    in_service: number;
    module: string | null;
    console_url: string | null;
    manage_url: string | null;
};

defineProps<{ servicePoints: ServicePoint[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Service Queues', href: '/queues' }],
    },
});

const icons = LucideIcons as unknown as Record<string, LucideIcon>;
const iconFor = (name: string | null): LucideIcon =>
    (name && icons[name]) || ListChecks;
</script>

<template>
    <Head title="Service Queues" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Service Queues
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Live patient queues across the facility. Attend to patients from
                a module's console; manage a queue to reassign, re-route or
                cancel entries.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="sp in servicePoints"
                :key="sp.slug"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-5"
                :class="sp.console_url || sp.manage_url ? '' : 'opacity-70'"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <component :is="iconFor(sp.icon)" class="size-5" />
                    </span>
                    <Lock
                        v-if="!sp.console_url && !sp.manage_url"
                        class="size-4 text-muted-foreground"
                    />
                </div>
                <div>
                    <h2 class="font-semibold">{{ sp.name }}</h2>
                    <p
                        v-if="sp.description"
                        class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                    >
                        {{ sp.description }}
                    </p>
                </div>
                <div class="flex gap-4 text-sm">
                    <span class="flex items-baseline gap-1">
                        <span class="text-lg font-semibold tabular-nums">{{
                            sp.waiting
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >waiting</span
                        >
                    </span>
                    <span class="flex items-baseline gap-1">
                        <span class="text-lg font-semibold tabular-nums">{{
                            sp.in_service
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >in service</span
                        >
                    </span>
                </div>
                <div
                    v-if="sp.console_url || sp.manage_url"
                    class="mt-auto flex flex-wrap gap-2"
                >
                    <Button v-if="sp.console_url" as-child size="sm">
                        <Link :href="sp.console_url">
                            Open {{ sp.module ?? 'console' }}
                        </Link>
                    </Button>
                    <Button
                        v-if="sp.manage_url"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <Link :href="sp.manage_url">
                            <SlidersHorizontal class="size-4" />
                            Manage queue
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
