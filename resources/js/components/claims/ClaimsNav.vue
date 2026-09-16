<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileCheck, HandCoins, Landmark } from '@lucide/vue';

defineProps<{
    current: 'claims' | 'batches' | 'payers';
}>();

const tabs = [
    { key: 'claims', title: 'Claims', href: '/claims', icon: HandCoins },
    {
        key: 'batches',
        title: 'Schedules',
        href: '/claims/batches',
        icon: FileCheck,
    },
    { key: 'payers', title: 'Payers', href: '/claims/payers', icon: Landmark },
] as const;
</script>

<template>
    <nav
        class="flex gap-1 border-b border-border print:hidden"
        aria-label="Claims sections"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition-colors"
            :class="
                tab.key === current
                    ? 'border-primary font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            :aria-current="tab.key === current ? 'page' : undefined"
        >
            <component :is="tab.icon" class="size-4" />
            {{ tab.title }}
        </Link>
    </nav>
</template>
