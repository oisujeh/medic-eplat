<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, GitMerge, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dismiss, merge } from '@/routes/patients/duplicates';

/**
 * Two records side by side, with the decision: keep one and merge the other
 * into it, or mark them as different people.
 */
type PatientRecord = {
    id: number;
    file_number: string;
    name: string;
    title: string | null;
    surname: string;
    first_name: string;
    other_names: string | null;
    sex_label: string;
    age: number | null;
    dob: string | null;
    dob_estimated: boolean;
    nin: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    state: string | null;
    lga: string | null;
    ward: string | null;
    next_of_kin: string | null;
    coverage: string;
    hmo: string | null;
    mother: string | null;
    identifiers: string[];
    registered_at: string | null;
    activity: Record<string, number>;
    merged: boolean;
    url: string;
};

const props = defineProps<{
    candidate: {
        id: number;
        score: number;
        level: string;
        level_label: string;
        reasons: string[];
        status: 'pending' | 'dismissed' | 'merged';
        status_label: string;
        reviewed_by: string | null;
        reviewed_at: string | null;
        notes: string | null;
    };
    records: [PatientRecord, PatientRecord];
    canMerge: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Patients', href: '/patients' },
            { title: 'Possible duplicates', href: '/patients/duplicates' },
            { title: 'Review', href: '#' },
        ],
    },
});

const pending = computed(() => props.candidate.status === 'pending');

const fields: Array<{ label: string; key: keyof PatientRecord }> = [
    { label: 'File number', key: 'file_number' },
    { label: 'Surname', key: 'surname' },
    { label: 'First name', key: 'first_name' },
    { label: 'Other names', key: 'other_names' },
    { label: 'Sex', key: 'sex_label' },
    { label: 'Date of birth', key: 'dob' },
    { label: 'NIN', key: 'nin' },
    { label: 'Phone', key: 'phone' },
    { label: 'Email', key: 'email' },
    { label: 'Address', key: 'address' },
    { label: 'LGA', key: 'lga' },
    { label: 'Ward', key: 'ward' },
    { label: 'Next of kin', key: 'next_of_kin' },
    { label: 'Coverage', key: 'coverage' },
    { label: 'HMO', key: 'hmo' },
    { label: 'Mother', key: 'mother' },
    { label: 'Registered', key: 'registered_at' },
];

const activityLabels: Record<string, string> = {
    visits: 'Visits',
    encounters: 'Encounters',
    lab_orders: 'Lab orders',
    medications: 'Medications',
    admissions: 'Admissions',
    bills: 'Bills',
    appointments: 'Appointments',
};

const differs = (key: keyof PatientRecord) =>
    (props.records[0][key] ?? null) !== (props.records[1][key] ?? null);

const display = (r: PatientRecord, key: keyof PatientRecord): string => {
    const v = r[key];

    if (key === 'dob' && v && r.dob_estimated) {
        return `${v} (estimated)`;
    }

    return v === null || v === undefined || v === '' ? '—' : String(v);
};

// The record with more activity is the natural survivor.
const total = (r: PatientRecord) =>
    Object.values(r.activity).reduce((sum, n) => sum + n, 0);
const suggested =
    total(props.records[1]) > total(props.records[0])
        ? props.records[1].id
        : props.records[0].id;

const mergeForm = useForm({ survivor_id: suggested, reason: '' });
const dismissForm = useForm({ notes: '' });
const confirming = ref(false);

const survivor = computed(
    () => props.records.find((r) => r.id === mergeForm.survivor_id) ?? props.records[0],
);
const duplicate = computed(
    () => props.records.find((r) => r.id !== mergeForm.survivor_id) ?? props.records[1],
);

function submitMerge() {
    mergeForm
        .transform((d) => ({ ...d, reason: d.reason || null }))
        .post(merge.url(props.candidate.id), { preserveScroll: true });
}

function submitDismiss() {
    dismissForm
        .transform((d) => ({ notes: d.notes || null }))
        .post(dismiss.url(props.candidate.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Review possible duplicate" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 p-4">
        <Link
            href="/patients/duplicates"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to the queue
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Are these the same person?
                </h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge :variant="props.candidate.level === 'auto' ? 'destructive' : 'secondary'">
                        {{ props.candidate.level_label }}
                    </Badge>
                    <span class="font-mono text-xs">score {{ props.candidate.score }}</span>
                    <span>· {{ props.candidate.reasons.join(' · ') }}</span>
                </p>
            </div>
            <div
                v-if="!pending"
                class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
            >
                <span class="font-medium">{{ props.candidate.status_label }}</span>
                <span v-if="props.candidate.reviewed_by" class="text-muted-foreground">
                    · {{ props.candidate.reviewed_by }}, {{ props.candidate.reviewed_at }}
                </span>
                <div v-if="props.candidate.notes" class="text-xs text-muted-foreground">
                    {{ props.candidate.notes }}
                </div>
            </div>
        </div>

        <!-- Side-by-side comparison -->
        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs text-muted-foreground">
                        <th class="px-4 py-3 font-medium">Field</th>
                        <th
                            v-for="r in props.records"
                            :key="r.id"
                            class="px-4 py-3 font-medium"
                        >
                            <Link :href="r.url" target="_blank" class="text-foreground hover:underline">
                                {{ r.name }}
                            </Link>
                            <span
                                v-if="pending && props.canMerge && r.id === mergeForm.survivor_id"
                                class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                                >Keeping this one</span
                            >
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="f in fields" :key="f.key">
                        <td class="px-4 py-2 text-muted-foreground">{{ f.label }}</td>
                        <td
                            v-for="r in props.records"
                            :key="r.id"
                            class="px-4 py-2"
                            :class="differs(f.key) ? 'font-medium' : 'text-muted-foreground'"
                        >
                            {{ display(r, f.key) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2 text-muted-foreground">Other identifiers</td>
                        <td v-for="r in props.records" :key="r.id" class="px-4 py-2">
                            {{ r.identifiers.length ? r.identifiers.join(', ') : '—' }}
                        </td>
                    </tr>
                    <tr
                        v-for="(label, key) in activityLabels"
                        :key="key"
                        class="bg-muted/20"
                    >
                        <td class="px-4 py-2 text-muted-foreground">{{ label }}</td>
                        <td
                            v-for="r in props.records"
                            :key="r.id"
                            class="px-4 py-2 tabular-nums"
                        >
                            {{ r.activity[key] ?? 0 }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Decision -->
        <div v-if="pending" class="grid gap-4 lg:grid-cols-2">
            <section
                v-if="props.canMerge"
                class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5"
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <GitMerge class="size-4" />
                    Merge into one record
                </h2>
                <p class="text-xs text-muted-foreground">
                    Everything on the other record moves to the one you keep:
                    visits, notes, results, bills. Blank details are filled from
                    the merged record, and its file number keeps working. This
                    cannot be undone from the screen.
                </p>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-xs font-medium">Keep</legend>
                    <label
                        v-for="r in props.records"
                        :key="r.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-2 text-sm"
                        :class="mergeForm.survivor_id === r.id ? 'border-primary bg-primary/5' : ''"
                    >
                        <input
                            v-model="mergeForm.survivor_id"
                            type="radio"
                            name="survivor_id"
                            :value="r.id"
                            class="accent-primary"
                        />
                        <span>
                            <span class="font-medium">{{ r.name }}</span>
                            <span class="ml-1 font-mono text-xs text-muted-foreground">{{ r.file_number }}</span>
                            <span class="ml-1 text-xs text-muted-foreground">· {{ total(r) }} records</span>
                        </span>
                    </label>
                    <InputError :message="mergeForm.errors.survivor_id" />
                </fieldset>

                <div class="grid gap-1.5">
                    <Label for="merge-reason">Reason</Label>
                    <Input
                        id="merge-reason"
                        v-model="mergeForm.reason"
                        maxlength="500"
                        placeholder="e.g. Registered twice at the desk on different days"
                    />
                    <InputError :message="mergeForm.errors.reason" />
                </div>

                <div v-if="!confirming">
                    <Button type="button" @click="confirming = true">
                        <GitMerge class="size-4" />
                        Merge {{ duplicate.file_number }} into {{ survivor.file_number }}
                    </Button>
                </div>
                <div
                    v-else
                    class="flex flex-wrap items-center gap-2 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm"
                >
                    <span class="mr-auto">
                        Merge <strong>{{ duplicate.name }}</strong> ({{ duplicate.file_number }})
                        into <strong>{{ survivor.name }}</strong> ({{ survivor.file_number }})?
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="mergeForm.processing"
                        @click="confirming = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="mergeForm.processing"
                        @click="submitMerge"
                    >
                        <Spinner v-if="mergeForm.processing" />
                        <Check v-else class="size-4" />
                        Yes, merge
                    </Button>
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <X class="size-4" />
                    Different people
                </h2>
                <p class="text-xs text-muted-foreground">
                    Take the pair off the queue. Editing either record later will
                    not bring it back.
                </p>
                <div class="grid gap-1.5">
                    <Label for="dismiss-notes">Note</Label>
                    <Input
                        id="dismiss-notes"
                        v-model="dismissForm.notes"
                        maxlength="500"
                        placeholder="e.g. Father and son with the same names"
                    />
                    <InputError :message="dismissForm.errors.notes" />
                </div>
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="dismissForm.processing"
                        @click="submitDismiss"
                    >
                        <Spinner v-if="dismissForm.processing" />
                        Mark as different people
                    </Button>
                </div>
            </section>
        </div>
    </div>
</template>
