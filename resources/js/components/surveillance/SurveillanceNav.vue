<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Radar } from '@lucide/vue';
import { index as casesIndex } from '@/routes/surveillance';
import { index as diseasesIndex } from '@/routes/surveillance/diseases';

defineProps<{
    current: 'cases' | 'diseases';
}>();

const tabs = [
    { key: 'cases', title: 'Case register', href: casesIndex(), icon: Radar },
    {
        key: 'diseases',
        title: 'Notifiable diseases',
        href: diseasesIndex(),
        icon: BookOpen,
    },
] as const;
</script>

<template>
    <nav
        class="flex gap-1 border-b border-border"
        aria-label="Surveillance sections"
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
