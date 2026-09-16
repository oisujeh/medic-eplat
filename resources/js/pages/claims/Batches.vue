<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ClaimsNav from '@/components/claims/ClaimsNav.vue';
import { Button } from '@/components/ui/button';
import { naira } from '@/lib/money';

type Batch = {
    id: number;
    batch_number: string;
    payer: string;
    payer_code: string;
    period: string;
    period_label: string;
    status: string;
    status_label: string;
    tone: string;
    claims_count: number;
    payer_amount: number;
    paid_amount: number;
    submitted_at: string | null;
    submitted_by: string | null;
    reference: string | null;
    url: string;
};

defineProps<{ batches: Batch[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Claims', href: '/claims' },
            { title: 'Schedules', href: '/claims/batches' },
        ],
    },
});

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}
</script>

<template>
    <Head title="Claims schedules" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Claims schedules
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                One schedule per payer per month. Submitted claims collect in
                the open schedule until it is sent.
            </p>
        </div>

        <ClaimsNav current="batches" />

        <div
            v-if="!batches.length"
            class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
        >
            No schedules yet. Submitting a claim opens this month's schedule for
            its payer.
        </div>
        <div
            v-else
            class="overflow-x-auto rounded-xl border border-border bg-card"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Schedule</th>
                        <th class="px-4 py-2.5 font-medium">Payer</th>
                        <th class="px-4 py-2.5 font-medium">Period</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Claims
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Claimed
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Remitted
                        </th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="b in batches" :key="b.id">
                        <td
                            class="px-4 py-2.5 font-mono text-xs whitespace-nowrap"
                        >
                            {{ b.batch_number }}
                        </td>
                        <td class="px-4 py-2.5">{{ b.payer }}</td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            {{ b.period_label }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ b.claims_count }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ naira(b.payer_amount) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ naira(b.paid_amount) }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="toneClass(b.tone)"
                                >{{ b.status_label }}</span
                            >
                            <p
                                v-if="b.submitted_at"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ b.submitted_at }}
                            </p>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <Button as-child size="sm" variant="outline">
                                <Link :href="b.url">Open</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
