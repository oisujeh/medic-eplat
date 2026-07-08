<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';

defineProps<{
    module: {
        name: string;
        slug: string;
        description: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [],
    },
});
</script>

<template>
    <Head :title="module.name" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ module.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    module.description ??
                    'This module is a placeholder. Its screens will be built here.'
                }}
            </p>
        </div>

        <div
            class="relative min-h-[60vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
