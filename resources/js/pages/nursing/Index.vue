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
        breadcrumbs: [{ title: 'Nursing', href: '/nursing' }],
    },
});
</script>

<template>
    <Head title="Nursing" />

    <Worklist
        :queue="queue"
        :recent="recent"
        :sees-all="seesAll"
        variant="nursing"
        title="Nursing"
        description="Nursing worklist — triage, antenatal care, family planning and immunization."
        empty-text="No patients awaiting nursing care. Patients routed to a nursing service point will appear here."
        recent-title="Your recent nursing notes"
        recent-empty="No signed nursing notes yet."
    />
</template>
