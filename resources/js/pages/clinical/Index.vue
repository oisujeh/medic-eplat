<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Worklist from '@/components/encounter/Worklist.vue';
import type { RecentEncounter, WorklistEntry } from '@/types/clinical';

defineProps<{
    queue: WorklistEntry[];
    seesAll: boolean;
    recent: RecentEncounter[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clinical', href: '/clinical' }],
    },
});
</script>

<template>
    <Head title="Clinical" />

    <Worklist
        :queue="queue"
        :recent="recent"
        :sees-all="seesAll"
        variant="clinical"
        title="Clinical"
        description="Consultation queue."
        empty-text="No patients awaiting consultation. Patients routed to a clinical service point will appear here."
        recent-title="Your recent consultations"
        recent-empty="No signed consultations yet."
    />
</template>
