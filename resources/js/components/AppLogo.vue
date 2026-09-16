<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();

// Once set up, the sidebar carries the facility's own name with the product
// name beneath it; before that only the product name is known.
const facilityName = computed(() => page.props.facility?.name ?? null);
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            facilityName ?? 'Medic-EPlat HIS'
        }}</span>
        <span
            v-if="facilityName"
            class="truncate text-xs leading-tight text-muted-foreground"
            >Medic-EPlat HIS</span
        >
    </div>
</template>
