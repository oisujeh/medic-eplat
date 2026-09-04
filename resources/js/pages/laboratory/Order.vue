<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    ExternalLink,
    FlaskConical,
    TestTube,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Result = {
    id: number;
    name: string;
    code: string | null;
    department: string | null;
    specimen: string | null;
    value: string | null;
    unit: string | null;
    reference_range: string | null;
    flag: string | null;
    status: string;
    notes: string | null;
};

const props = defineProps<{
    order: {
        id: number;
        accession_number: string;
        status: string;
        status_label: string;
        tone: string;
        priority: string;
        priority_label: string;
        clinical_details: string | null;
        specimen_type: string | null;
        ordered_by: string | null;
        ordered_at: string | null;
        collected_by: string | null;
        collected_at: string | null;
        received_at: string | null;
        verified_by: string | null;
        verified_at: string | null;
        cancelled_reason: string | null;
    };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex_label: string;
        age: number | null;
        url: string;
    };
    results: Result[];
    flags: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Laboratory', href: '/laboratory' },
            { title: 'Requisition', href: '#' },
        ],
    },
});

const base = `/laboratory/${props.order.id}`;
const editable = computed(() => props.order.status === 'in_progress');

const steps = [
    { key: 'ordered', label: 'Ordered' },
    { key: 'collected', label: 'Collected' },
    { key: 'in_progress', label: 'In progress' },
    { key: 'completed', label: 'Completed' },
];
const stepIndex = computed(() => {
    const i = steps.findIndex((s) => s.key === props.order.status);

    return props.order.status === 'completed' ? steps.length : i;
});

const collectForm = useForm({ specimen_type: props.order.specimen_type ?? '' });
const cancelForm = useForm({ reason: '' });
const resultForm = useForm<{
    results: Record<number, { value: string; flag: string; notes: string }>;
}>({
    results: Object.fromEntries(
        props.results.map((r) => [
            r.id,
            {
                value: r.value ?? '',
                flag: r.flag ?? 'auto',
                notes: r.notes ?? '',
            },
        ]),
    ),
});

const allValued = computed(() =>
    props.results.every((r) => (resultForm.results[r.id]?.value ?? '') !== ''),
);

function collect() {
    collectForm.post(`${base}/collect`, { preserveScroll: true });
}
function receive() {
    router.post(`${base}/receive`, {}, { preserveScroll: true });
}
function transformedResults() {
    return {
        results: Object.fromEntries(
            Object.entries(resultForm.results).map(([id, r]) => [
                id,
                {
                    value: r.value || null,
                    flag: r.flag === 'auto' ? null : r.flag,
                    notes: r.notes || null,
                },
            ]),
        ),
    };
}
function saveResults() {
    resultForm
        .transform(transformedResults)
        .post(`${base}/results`, { preserveScroll: true });
}
function verify() {
    resultForm.transform(transformedResults).post(`${base}/results`, {
        preserveScroll: true,
        onSuccess: () =>
            router.post(`${base}/verify`, {}, { preserveScroll: true }),
    });
}
function cancel() {
    cancelForm.post(`${base}/cancel`, { preserveScroll: true });
}

function flagClass(flag: string | null): string {
    if (flag === 'critical') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400 ring-1 ring-red-500/30';
    }

    if (flag === 'high' || flag === 'low' || flag === 'abnormal') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/30';
    }

    return 'text-foreground';
}
function toneClass(tone: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        green: 'bg-green-500/10 text-green-700 dark:text-green-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}
</script>

<template>
    <Head :title="`Lab — ${order.accession_number}`" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/laboratory"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to worklist
        </Link>

        <!-- Patient + order header -->
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary"
                    >
                        {{ patient.initials }}
                    </span>
                    <div>
                        <h1 class="text-lg font-semibold tracking-tight">
                            {{ patient.name }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            <span class="font-mono">{{
                                patient.file_number
                            }}</span>
                            · {{ patient.sex_label
                            }}{{
                                patient.age !== null
                                    ? ' · ' + patient.age + 'y'
                                    : ''
                            }}
                        </p>
                        <p
                            class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                class="font-mono font-medium text-foreground"
                                >{{ order.accession_number }}</span
                            >
                            <span
                                v-if="order.priority !== 'normal'"
                                class="rounded-full bg-red-500/10 px-2 py-0.5 font-semibold text-red-700 dark:text-red-400"
                                >{{ order.priority_label }}</span
                            >
                            <span
                                class="rounded-full px-2 py-0.5 font-medium"
                                :class="toneClass(order.tone)"
                                >{{ order.status_label }}</span
                            >
                        </p>
                    </div>
                </div>
                <Button as-child variant="outline" size="sm">
                    <Link :href="patient.url">
                        <ExternalLink class="size-4" />
                        Full profile
                    </Link>
                </Button>
            </div>

            <!-- Workflow stepper -->
            <div class="mt-5 flex items-center gap-1">
                <template v-for="(step, i) in steps" :key="step.key">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="flex size-6 items-center justify-center rounded-full text-[11px] font-semibold"
                            :class="
                                i < stepIndex
                                    ? 'bg-primary text-primary-foreground'
                                    : i === stepIndex
                                      ? 'bg-primary/15 text-primary ring-1 ring-primary'
                                      : 'bg-muted text-muted-foreground'
                            "
                            >{{ i + 1 }}</span
                        >
                        <span
                            class="text-xs"
                            :class="
                                i <= stepIndex
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground'
                            "
                            >{{ step.label }}</span
                        >
                    </div>
                    <div
                        v-if="i < steps.length - 1"
                        class="h-px flex-1 bg-border"
                    />
                </template>
            </div>

            <dl
                class="mt-4 grid gap-x-6 gap-y-1.5 text-xs sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-if="order.clinical_details"
                    class="sm:col-span-2 lg:col-span-3"
                >
                    <dt class="text-muted-foreground">Clinical details</dt>
                    <dd class="text-foreground">
                        {{ order.clinical_details }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Ordered by</dt>
                    <dd class="text-foreground">
                        {{ order.ordered_by ?? '—' }}
                        <span
                            v-if="order.ordered_at"
                            class="text-muted-foreground"
                            >· {{ order.ordered_at }}</span
                        >
                    </dd>
                </div>
                <div v-if="order.specimen_type">
                    <dt class="text-muted-foreground">Specimen</dt>
                    <dd class="text-foreground">{{ order.specimen_type }}</dd>
                </div>
                <div v-if="order.collected_by">
                    <dt class="text-muted-foreground">Collected by</dt>
                    <dd class="text-foreground">
                        {{ order.collected_by }}
                        <span
                            v-if="order.collected_at"
                            class="text-muted-foreground"
                            >· {{ order.collected_at }}</span
                        >
                    </dd>
                </div>
                <div v-if="order.verified_by">
                    <dt class="text-muted-foreground">Verified by</dt>
                    <dd class="text-foreground">
                        {{ order.verified_by }}
                        <span
                            v-if="order.verified_at"
                            class="text-muted-foreground"
                            >· {{ order.verified_at }}</span
                        >
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Cancelled notice -->
        <div
            v-if="order.status === 'cancelled'"
            class="rounded-xl border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-700 dark:text-red-400"
        >
            This requisition was cancelled.
            <span v-if="order.cancelled_reason"
                >Reason: {{ order.cancelled_reason }}</span
            >
        </div>

        <!-- Stage action: collect -->
        <section
            v-if="order.status === 'ordered'"
            class="rounded-xl border border-border bg-card p-5"
        >
            <h2 class="mb-1 flex items-center gap-1.5 text-sm font-semibold">
                <TestTube class="size-4 text-primary" />
                Collect specimen
            </h2>
            <p class="mb-4 text-xs text-muted-foreground">
                Record the specimen to move this requisition into the lab
                pipeline.
            </p>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="collect"
            >
                <div class="grid gap-1.5">
                    <Label>Specimen type</Label>
                    <Input
                        v-model="collectForm.specimen_type"
                        placeholder="e.g. EDTA blood"
                        class="w-56"
                    />
                </div>
                <Button type="submit" :disabled="collectForm.processing">
                    <TestTube class="size-4" />
                    Mark collected
                </Button>
            </form>
        </section>

        <!-- Stage action: receive -->
        <section
            v-else-if="order.status === 'collected'"
            class="rounded-xl border border-border bg-card p-5"
        >
            <h2 class="mb-1 flex items-center gap-1.5 text-sm font-semibold">
                <FlaskConical class="size-4 text-primary" />
                Receive at bench
            </h2>
            <p class="mb-4 text-xs text-muted-foreground">
                Confirm the specimen has arrived and open it for analysis.
            </p>
            <Button type="button" @click="receive">
                <FlaskConical class="size-4" />
                Receive & start analysis
            </Button>
        </section>

        <!-- Results grid -->
        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-4 flex items-center gap-1.5 text-sm font-semibold">
                <FlaskConical class="size-4 text-primary" />
                Results
                <span class="font-normal text-muted-foreground"
                    >({{ results.length }})</span
                >
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-3 font-medium">Test</th>
                            <th class="py-2 pr-3 font-medium">Result</th>
                            <th class="py-2 pr-3 font-medium">Unit</th>
                            <th class="py-2 pr-3 font-medium">Reference</th>
                            <th class="py-2 font-medium">Flag</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="r in results" :key="r.id">
                            <td class="py-2 pr-3">
                                <div class="font-medium text-foreground">
                                    {{ r.name }}
                                </div>
                                <div
                                    v-if="r.department"
                                    class="text-[11px] text-muted-foreground capitalize"
                                >
                                    {{ r.department }}
                                </div>
                            </td>
                            <td class="py-2 pr-3">
                                <Input
                                    v-if="editable"
                                    v-model="resultForm.results[r.id].value"
                                    class="h-8 w-32"
                                    placeholder="—"
                                />
                                <span
                                    v-else
                                    class="rounded px-1.5 font-medium"
                                    :class="flagClass(r.flag)"
                                    >{{ r.value ?? '—' }}</span
                                >
                            </td>
                            <td class="py-2 pr-3 text-muted-foreground">
                                {{ r.unit ?? '' }}
                            </td>
                            <td class="py-2 pr-3 text-muted-foreground">
                                {{ r.reference_range ?? '—' }}
                            </td>
                            <td class="py-2">
                                <Select
                                    v-if="editable"
                                    v-model="resultForm.results[r.id].flag"
                                >
                                    <SelectTrigger class="h-8 w-28">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="auto"
                                            >Auto</SelectItem
                                        >
                                        <SelectItem
                                            v-for="f in flags"
                                            :key="f.value"
                                            :value="f.value"
                                            >{{ f.label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <span
                                    v-else-if="r.flag && r.flag !== 'normal'"
                                    class="rounded px-1.5 text-xs font-medium capitalize"
                                    :class="flagClass(r.flag)"
                                    >{{ r.flag }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="editable" class="mt-5 flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="resultForm.processing"
                    @click="saveResults"
                >
                    Save draft
                </Button>
                <Button
                    type="button"
                    :disabled="resultForm.processing || !allValued"
                    @click="verify"
                >
                    <CheckCircle2 class="size-4" />
                    Verify &amp; release
                </Button>
                <span v-if="!allValued" class="text-xs text-muted-foreground">
                    Enter every value to release the order.
                </span>
            </div>
            <p
                v-else-if="
                    order.status !== 'completed' && order.status !== 'cancelled'
                "
                class="mt-2 text-xs text-muted-foreground"
            >
                Results can be entered once the specimen is received.
            </p>
        </section>

        <!-- Cancel -->
        <section
            v-if="
                order.status === 'ordered' ||
                order.status === 'collected' ||
                order.status === 'in_progress'
            "
            class="rounded-xl border border-border bg-card p-5"
        >
            <h2 class="mb-3 text-sm font-semibold">Cancel requisition</h2>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="cancel"
            >
                <div class="grid flex-1 gap-1.5">
                    <Label>Reason (optional)</Label>
                    <Input
                        v-model="cancelForm.reason"
                        placeholder="e.g. Duplicate order"
                    />
                    <InputError :message="cancelForm.errors.reason" />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                    :disabled="cancelForm.processing"
                >
                    <Ban class="size-4" />
                    Cancel order
                </Button>
            </form>
        </section>
    </div>
</template>
