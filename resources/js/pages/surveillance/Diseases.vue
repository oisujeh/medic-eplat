<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import SurveillanceNav from '@/components/surveillance/SurveillanceNav.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { update } from '@/routes/surveillance/diseases';

type Disease = {
    id: number;
    name: string;
    category: 'immediate' | 'weekly';
    category_label: string;
    detection: 'diagnosis' | 'event';
    icd_prefixes: string[];
    case_definition: string | null;
    notification_hours: number | null;
    requires_contact_tracing: boolean;
    is_active: boolean;
    cases_count: number;
};

const props = defineProps<{
    diseases: Disease[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Case surveillance', href: '/surveillance' },
            { title: 'Notifiable diseases', href: '/surveillance/diseases' },
        ],
    },
});

const groups = computed(() => [
    {
        key: 'immediate',
        title: 'Immediately notifiable',
        sub: 'Notify the LGA DSNO within 24 hours of a suspected case.',
        rows: props.diseases.filter((d) => d.category === 'immediate'),
    },
    {
        key: 'weekly',
        title: 'Weekly reportable',
        sub: 'Counted on the IDSR 002 weekly return.',
        rows: props.diseases.filter((d) => d.category === 'weekly'),
    },
]);

function toggle(disease: Disease, on: boolean) {
    router.patch(
        update(disease.id).url,
        { is_active: on },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Notifiable diseases" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Notifiable diseases
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Nigeria's IDSR priority diseases and the ICD-10 codes that open
                a case when coded on a diagnosis. Switch a disease off to stop
                detecting it.
            </p>
        </div>

        <SurveillanceNav current="diseases" />

        <div
            v-for="group in groups"
            :key="group.key"
            class="flex flex-col gap-2"
        >
            <div>
                <h2 class="flex items-center gap-1.5 text-sm font-semibold">
                    <AlertTriangle
                        v-if="group.key === 'immediate'"
                        class="size-4 text-red-600 dark:text-red-400"
                    />
                    {{ group.title }}
                </h2>
                <p class="text-xs text-muted-foreground">{{ group.sub }}</p>
            </div>
            <div
                class="overflow-x-auto rounded-xl border border-border bg-card"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="w-10 px-4 py-2.5"></th>
                            <th class="px-4 py-2.5 font-medium">Disease</th>
                            <th class="px-4 py-2.5 font-medium">ICD-10</th>
                            <th class="px-4 py-2.5 font-medium">
                                Case definition
                            </th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Cases
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="d in group.rows"
                            :key="d.id"
                            :class="
                                d.is_active
                                    ? ''
                                    : 'bg-muted/30 text-muted-foreground'
                            "
                        >
                            <td class="px-4 py-2.5">
                                <Checkbox
                                    :model-value="d.is_active"
                                    :aria-label="`Detect ${d.name}`"
                                    @update:model-value="
                                        (v: boolean | 'indeterminate') =>
                                            toggle(d, v === true)
                                    "
                                />
                            </td>
                            <td
                                class="px-4 py-2.5 font-medium whitespace-nowrap"
                            >
                                {{ d.name }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    v-if="d.detection === 'diagnosis'"
                                    class="font-mono text-xs"
                                    >{{ d.icd_prefixes.join(', ') }}</span
                                >
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >Reported by module</span
                                >
                                <div
                                    class="mt-0.5 flex flex-wrap gap-1 text-[11px]"
                                >
                                    <span
                                        v-if="d.notification_hours !== null"
                                        class="rounded bg-red-500/10 px-1 text-red-700 dark:text-red-400"
                                        >{{ d.notification_hours }} h</span
                                    >
                                    <span
                                        v-if="d.requires_contact_tracing"
                                        class="rounded bg-muted px-1 text-muted-foreground"
                                        >Contact tracing</span
                                    >
                                </div>
                            </td>
                            <td
                                class="max-w-md px-4 py-2.5 text-xs text-muted-foreground"
                            >
                                {{ d.case_definition ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums">
                                {{ d.cases_count }}
                            </td>
                        </tr>
                        <tr v-if="!group.rows.length">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-sm text-muted-foreground"
                            >
                                No diseases in this group.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
