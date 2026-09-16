<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, ListChecks, SlidersHorizontal } from '@lucide/vue';
import { index as issuesIndex, scorecard } from '@/routes/data-quality';
import { index as rulesIndex } from '@/routes/data-quality/rules';

defineProps<{
    current: 'issues' | 'scorecard' | 'rules';
}>();

const tabs = [
    {
        key: 'issues',
        title: 'Issue queue',
        href: issuesIndex(),
        icon: ListChecks,
    },
    {
        key: 'scorecard',
        title: 'Scorecard',
        href: scorecard(),
        icon: BadgeCheck,
    },
    {
        key: 'rules',
        title: 'Rules',
        href: rulesIndex(),
        icon: SlidersHorizontal,
    },
] as const;
</script>

<template>
    <nav
        class="flex gap-1 border-b border-border"
        aria-label="Data quality sections"
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
