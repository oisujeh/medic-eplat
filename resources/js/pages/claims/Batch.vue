<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { naira } from '@/lib/money';
import type { SharedData } from '@/types';

const props = defineProps<{
    batch: {
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
        notes: string | null;
    };
    claims: Array<{
        id: number;
        claim_number: string;
        patient: string;
        file_number: string;
        enrollee_number: string | null;
        service_date: string;
        diagnosis: string | null;
        authorization_code: string | null;
        gross_amount: number;
        copay_amount: number;
        payer_amount: number;
        paid_amount: number;
        status: string;
        status_label: string;
        tone: string;
        url: string;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Claims', href: '/claims' },
            { title: 'Schedules', href: '/claims/batches' },
            { title: 'Schedule', href: '#' },
        ],
    },
});

const page = usePage<SharedData>();
const facility = computed(() => page.props.facility);
const serviceError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.status,
);

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-primary/10 text-primary',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        red: 'bg-red-500/10 text-red-700 dark:text-red-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

const submitOpen = ref(false);
const submitForm = useForm({ reference: '', notes: '' });

function submitBatch() {
    submitForm.post(`/claims/batches/${props.batch.id}/submit`, {
        preserveScroll: true,
        onSuccess: () => {
            submitOpen.value = false;
        },
    });
}

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Schedule ${batch.batch_number}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-wrap items-start justify-between gap-4 print:hidden"
        >
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link href="/claims/batches" aria-label="Back to schedules">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        Schedule {{ batch.batch_number }}
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ batch.payer }} · {{ batch.period_label }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="toneClass(batch.tone)"
                            >{{ batch.status_label }}</span
                        >
                        <span
                            v-if="batch.submitted_at"
                            class="text-xs text-muted-foreground"
                        >
                            Sent {{ batch.submitted_at }} by
                            {{ batch.submitted_by ?? '—' }}
                            <span v-if="batch.reference">
                                · ref {{ batch.reference }}</span
                            >
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="print">
                    <Printer class="size-4" />
                    Print
                </Button>
                <Button
                    v-if="batch.status === 'open'"
                    @click="submitOpen = true"
                >
                    <Send class="size-4" />
                    Submit to payer
                </Button>
            </div>
        </div>

        <InputError v-if="serviceError" :message="serviceError" />

        <!-- Printable schedule -->
        <div
            class="rounded-xl border border-border bg-card p-5 print:border-0 print:p-0"
        >
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold">
                        {{ facility?.name ?? 'Facility' }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <span v-if="facility?.code"
                            >Facility code {{ facility.code }} ·
                        </span>
                        Claims schedule for {{ batch.payer }} ·
                        {{ batch.period_label }}
                    </p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-mono">{{ batch.batch_number }}</p>
                    <p class="text-muted-foreground">
                        {{ batch.claims_count }} claims ·
                        {{ naira(batch.payer_amount) }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">Claim</th>
                            <th class="px-3 py-2 font-medium">Enrollee</th>
                            <th class="px-3 py-2 font-medium">Service date</th>
                            <th class="px-3 py-2 font-medium">Diagnosis</th>
                            <th class="px-3 py-2 font-medium">Auth. code</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Gross
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Co-pay
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Claimed
                            </th>
                            <th class="px-3 py-2 font-medium print:hidden">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(c, i) in claims" :key="c.id">
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ i + 1 }}
                            </td>
                            <td
                                class="px-3 py-2 font-mono text-xs whitespace-nowrap"
                            >
                                <Link :href="c.url" class="hover:underline">{{
                                    c.claim_number
                                }}</Link>
                            </td>
                            <td class="px-3 py-2">
                                <p>{{ c.patient }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ c.enrollee_number ?? c.file_number }}
                                </p>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ c.service_date }}
                            </td>
                            <td class="max-w-56 px-3 py-2">
                                <p class="truncate" :title="c.diagnosis ?? ''">
                                    {{ c.diagnosis ?? '—' }}
                                </p>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">
                                {{ c.authorization_code ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ naira(c.gross_amount) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ naira(c.copay_amount) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-medium tabular-nums"
                            >
                                {{ naira(c.payer_amount) }}
                            </td>
                            <td
                                class="px-3 py-2 whitespace-nowrap print:hidden"
                            >
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="toneClass(c.tone)"
                                    >{{ c.status_label }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-border">
                        <tr>
                            <td colspan="6" class="px-3 py-2 font-medium">
                                Total
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{
                                    naira(
                                        claims.reduce(
                                            (n, c) => n + c.gross_amount,
                                            0,
                                        ),
                                    )
                                }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{
                                    naira(
                                        claims.reduce(
                                            (n, c) => n + c.copay_amount,
                                            0,
                                        ),
                                    )
                                }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-semibold tabular-nums"
                            >
                                {{ naira(batch.payer_amount) }}
                            </td>
                            <td class="print:hidden"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-8 hidden grid-cols-2 gap-8 text-sm print:grid">
                <div>
                    <p class="border-t border-border pt-2">Prepared by</p>
                </div>
                <div>
                    <p class="border-t border-border pt-2">
                        Received for {{ batch.payer }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Submit dialog -->
        <Dialog v-model:open="submitOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Submit schedule</DialogTitle>
                    <DialogDescription>
                        Marks the {{ batch.claims_count }} claims worth
                        {{ naira(batch.payer_amount) }} as sent to
                        {{ batch.payer }}. Later claims this month open a new
                        schedule.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitBatch">
                    <div class="grid gap-1.5">
                        <Label for="batch-ref">Payer reference</Label>
                        <Input
                            id="batch-ref"
                            v-model="submitForm.reference"
                            placeholder="Acknowledgement or portal reference"
                        />
                        <InputError :message="submitForm.errors.reference" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="batch-notes">Notes</Label>
                        <Input id="batch-notes" v-model="submitForm.notes" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="submitOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="submitForm.processing">
                            <Spinner v-if="submitForm.processing" />
                            <Send v-else class="size-4" />
                            Submit
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
