<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, Send } from '@lucide/vue';
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
import { notify, update } from '@/routes/surveillance';
import type { SharedData } from '@/types';

type Option = { value: string; label: string };

const props = defineProps<{
    case: {
        id: number;
        detected_at: string;
        detected_by: string | null;
        disease: string;
        category: 'immediate' | 'weekly';
        category_label: string;
        instruction: string;
        case_definition: string | null;
        requires_contact_tracing: boolean;
        classified_at: string | null;
        classified_by: string | null;
        notification_due_at: string | null;
        icd_code: string | null;
        patient: {
            id: number;
            name: string;
            file_number: string;
            sex: string;
            age: number | null;
            lga: string | null;
        };
        patient_details: {
            phone: string | null;
            address: string | null;
            lga: string | null;
            state: string | null;
            next_of_kin: string | null;
            next_of_kin_phone: string | null;
        };
        patient_url: string;
        encounter_url: string | null;
        problem: { name: string; code: string | null } | null;
        classification: string;
        classification_label: string;
        outcome: string;
        onset_date: string | null;
        notes: string | null;
        notification_status: string;
        notification_label: string;
        notification_tone: string;
        notified_at: string | null;
        notified_by: string | null;
        notified_to: string | null;
        notification_reference: string | null;
        overdue: boolean;
    };
    classifications: Option[];
    outcomes: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Case surveillance', href: '/surveillance' },
            { title: 'Case', href: '#' },
        ],
    },
});

const page = usePage<SharedData>();

const caseForm = useForm({
    classification: props.case.classification,
    outcome: props.case.outcome,
    onset_date: props.case.onset_date ?? '',
    notes: props.case.notes ?? '',
});

function saveCase() {
    caseForm
        .transform((data) => ({
            ...data,
            onset_date: data.onset_date || null,
            notes: data.notes || null,
        }))
        .patch(update(props.case.id).url, { preserveScroll: true });
}

const defaultNotifiedTo = computed(() =>
    page.props.facility.lga
        ? `DSNO, ${page.props.facility.lga} LGA`
        : 'LGA DSNO',
);

const notifyForm = useForm({
    notified_to: defaultNotifiedTo.value,
    notified_at: '',
    notification_reference: '',
    notes: '',
});

function recordNotification() {
    notifyForm
        .transform((data) => ({
            ...data,
            notified_at: data.notified_at || null,
            notification_reference: data.notification_reference || null,
            notes: data.notes || null,
        }))
        .post(notify(props.case.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                notifyForm.reset(
                    'notified_at',
                    'notification_reference',
                    'notes',
                ),
        });
}

const isPending = computed(() => props.case.notification_status === 'pending');
const isNotified = computed(
    () => props.case.notification_status === 'notified',
);

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';
</script>

<template>
    <Head :title="`${props.case.disease} — ${props.case.patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/surveillance"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Case register
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                >
                    <AlertTriangle
                        v-if="props.case.category === 'immediate'"
                        class="size-5 text-red-600 dark:text-red-400"
                    />
                    {{ props.case.disease }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.case.category_label
                    }}<template v-if="props.case.icd_code">
                        · ICD-10 {{ props.case.icd_code }}</template
                    >
                    · detected {{ props.case.detected_at
                    }}<template v-if="props.case.detected_by">
                        by {{ props.case.detected_by }}</template
                    >
                </p>
            </div>
            <div class="text-right">
                <Link
                    :href="props.case.patient_url"
                    class="font-medium hover:underline"
                    >{{ props.case.patient.name }}</Link
                >
                <p class="text-xs text-muted-foreground">
                    {{ props.case.patient.file_number }} ·
                    {{ props.case.patient.sex
                    }}<template v-if="props.case.patient.age !== null">
                        · {{ props.case.patient.age }}y</template
                    >
                </p>
            </div>
        </div>

        <!-- Notification status banner -->
        <div
            v-if="isPending"
            class="flex items-start gap-3 rounded-xl border border-red-500/40 bg-red-500/5 px-4 py-3 text-sm"
        >
            <AlertTriangle
                class="mt-0.5 size-4 shrink-0 text-red-600 dark:text-red-400"
            />
            <div>
                <p class="font-medium text-red-800 dark:text-red-300">
                    {{ props.case.instruction }}
                </p>
                <p
                    v-if="props.case.overdue"
                    class="mt-0.5 text-red-700 dark:text-red-400"
                >
                    The deadline of {{ props.case.notification_due_at }} has
                    passed and the notification has not been recorded.
                </p>
                <p v-else-if="props.case.notification_due_at" class="mt-0.5">
                    Due by {{ props.case.notification_due_at }}.
                </p>
                <p v-if="props.case.requires_contact_tracing" class="mt-0.5">
                    Contact tracing is expected for this disease.
                </p>
            </div>
        </div>
        <div
            v-else-if="isNotified"
            class="flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-sm"
        >
            <CheckCircle2
                class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
            />
            <p>
                Notified to
                <span class="font-medium">{{ props.case.notified_to }}</span> on
                {{ props.case.notified_at
                }}<template v-if="props.case.notified_by">
                    by {{ props.case.notified_by }}</template
                ><template v-if="props.case.notification_reference">
                    · ref {{ props.case.notification_reference }}</template
                >.
            </p>
        </div>
        <div
            v-else
            class="rounded-xl border border-border bg-card px-4 py-3 text-sm text-muted-foreground"
        >
            {{ props.case.instruction }}
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Case details -->
            <form
                class="rounded-xl border border-border bg-card p-5"
                @submit.prevent="saveCase"
            >
                <h2 class="mb-3 text-sm font-semibold">Case details</h2>

                <dl class="mb-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div v-if="props.case.problem" class="col-span-2">
                        <dt class="text-xs text-muted-foreground">Diagnosis</dt>
                        <dd>
                            {{ props.case.problem.name }}
                            <span
                                v-if="props.case.problem.code"
                                class="font-mono text-xs text-muted-foreground"
                                >{{ props.case.problem.code }}</span
                            >
                            <Link
                                v-if="props.case.encounter_url"
                                :href="props.case.encounter_url"
                                class="ml-2 text-xs text-muted-foreground hover:underline"
                                >Open encounter</Link
                            >
                        </dd>
                    </div>
                    <div v-if="props.case.case_definition" class="col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Standard case definition
                        </dt>
                        <dd class="text-muted-foreground">
                            {{ props.case.case_definition }}
                        </dd>
                    </div>
                </dl>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label>Classification</Label>
                        <Select v-model="caseForm.classification">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in classifications"
                                    :key="c.value"
                                    :value="c.value"
                                    >{{ c.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <p
                            v-if="props.case.classified_by"
                            class="text-xs text-muted-foreground"
                        >
                            {{ props.case.classification_label }} since
                            {{ props.case.classified_at }} by
                            {{ props.case.classified_by }}. Suspected → Probable
                            → Confirmed; confirmed cases cannot be reclassified.
                        </p>
                        <InputError :message="caseForm.errors.classification" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Outcome</Label>
                        <Select v-model="caseForm.outcome">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in outcomes"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="caseForm.errors.outcome" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Date of onset</Label>
                        <Input v-model="caseForm.onset_date" type="date" />
                        <InputError :message="caseForm.errors.onset_date" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Investigation notes</Label>
                        <textarea
                            v-model="caseForm.notes"
                            :class="textareaClass"
                            rows="4"
                            placeholder="Contacts, specimens sent, lab results, response actions"
                        />
                        <InputError :message="caseForm.errors.notes" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <Button type="submit" :disabled="caseForm.processing">
                        Save case
                    </Button>
                    <p
                        v-if="caseForm.recentlySuccessful"
                        class="text-sm text-muted-foreground"
                    >
                        Saved.
                    </p>
                </div>
            </form>

            <div class="flex flex-col gap-4">
                <!-- Contact details for the DSNO -->
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">
                        Patient contact for follow-up
                    </h2>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Phone</dt>
                            <dd>
                                {{ props.case.patient_details.phone ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                LGA / State
                            </dt>
                            <dd>
                                {{ props.case.patient_details.lga ?? '—' }}
                                <template
                                    v-if="props.case.patient_details.state"
                                >
                                    , {{ props.case.patient_details.state }}
                                </template>
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Address
                            </dt>
                            <dd>
                                {{ props.case.patient_details.address ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Next of kin
                            </dt>
                            <dd>
                                {{
                                    props.case.patient_details.next_of_kin ??
                                    '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Next of kin phone
                            </dt>
                            <dd>
                                {{
                                    props.case.patient_details
                                        .next_of_kin_phone ?? '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Notification -->
                <form
                    v-if="!isNotified"
                    class="rounded-xl border p-5"
                    :class="
                        isPending
                            ? 'border-red-500/40 bg-card'
                            : 'border-border bg-card'
                    "
                    @submit.prevent="recordNotification"
                >
                    <h2 class="mb-1 text-sm font-semibold">
                        Record DSNO notification
                    </h2>
                    <p class="mb-3 text-xs text-muted-foreground">
                        <template v-if="isPending">
                            Fill this in as soon as the DSNO has been reached by
                            phone, SMS or the IDSR 001 form.
                        </template>
                        <template v-else>
                            Weekly-reportable cases go on the IDSR 002 return.
                            Record a notification here only if the DSNO was told
                            individually.
                        </template>
                    </p>
                    <div class="grid gap-3">
                        <div class="grid gap-1.5">
                            <Label>Notified to *</Label>
                            <Input
                                v-model="notifyForm.notified_to"
                                placeholder="e.g. DSNO Ikeja LGA, Mr Adewale"
                            />
                            <InputError
                                :message="notifyForm.errors.notified_to"
                            />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label>When</Label>
                                <Input
                                    v-model="notifyForm.notified_at"
                                    type="datetime-local"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Leave blank for now.
                                </p>
                                <InputError
                                    :message="notifyForm.errors.notified_at"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Reference</Label>
                                <Input
                                    v-model="notifyForm.notification_reference"
                                    placeholder="IDSR 001 no., SORMAS ID"
                                />
                                <InputError
                                    :message="
                                        notifyForm.errors.notification_reference
                                    "
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Note</Label>
                            <Input
                                v-model="notifyForm.notes"
                                placeholder="How the DSNO was reached, anything they asked for"
                            />
                            <InputError :message="notifyForm.errors.notes" />
                        </div>
                    </div>
                    <Button
                        type="submit"
                        class="mt-4"
                        :variant="isPending ? 'default' : 'outline'"
                        :disabled="notifyForm.processing"
                    >
                        <Send class="size-4" />
                        Record notification
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
