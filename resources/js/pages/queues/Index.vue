<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import * as LucideIcons from '@lucide/vue';
import { ListChecks, Lock } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

type ServicePoint = {
    name: string;
    slug: string;
    icon: string | null;
    description: string | null;
    waiting: number;
    in_service: number;
    can_work: boolean;
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
            <h1 class="text-2xl font-semibold tracking-tight">Service Queues</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Live patient queues across service points. Open a queue you staff
                to call and attend to patients.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <component
                :is="sp.can_work ? Link : 'div'"
                v-for="sp in servicePoints"
                :key="sp.slug"
                :href="sp.can_work ? `/queues/${sp.slug}` : undefined"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-5 transition-colors"
                :class="
                    sp.can_work
                        ? 'cursor-pointer hover:border-primary/40 hover:bg-muted/40'
                        : 'opacity-70'
                "
            >
                <div class="flex items-center justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <component :is="iconFor(sp.icon)" class="size-5" />
                    </span>
                    <Lock
                        v-if="!sp.can_work"
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
                <div class="mt-auto flex gap-4 text-sm">
                    <span class="flex items-baseline gap-1">
                        <span class="text-lg font-semibold">{{ sp.waiting }}</span>
                        <span class="text-xs text-muted-foreground">waiting</span>
                    </span>
                    <span class="flex items-baseline gap-1">
                        <span class="text-lg font-semibold">{{
                            sp.in_service
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >in service</span
                        >
                    </span>
                </div>
            </component>
        </div>
    </div>
</template>
