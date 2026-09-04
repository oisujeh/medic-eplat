<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { autoGrow, textareaClass } from '@/lib/forms';
import type { Referral } from '@/types/clinical';

type Option = { value: string; label: string };

const props = defineProps<{
    referral: Referral & {
        patient: {
            id: number;
            name: string;
            file_number: string;
            sex: string;
            age: number | null;
            phone: string | null;
            url: string;
        };
        referred_by: string | null;
        closed_by: string | null;
        encounter_url: string | null;
        transitions: Option[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Referrals', href: '/referrals' },
            { title: 'Referral', href: '#' },
        ],
    },
});

const form = useForm({
    status: props.referral.transitions[0]?.value ?? '',
    feedback: '',
});

function save() {
    form.transform((d) => ({ ...d, feedback: d.feedback || null })).post(
        props.referral.urls.status,
        {
            preserveScroll: true,
            onSuccess: () => form.reset('feedback'),
            onFinish: () => form.transform((d) => d),
        },
    );
}

const TONES: Record<string, string> = {
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    muted: 'bg-muted text-muted-foreground',
};

const canChange = computed(() => props.referral.transitions.length > 0);
</script>

<template>
    <Head :title="`${referral.referral_number} — ${referral.patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/referrals"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Referral register
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ referral.destination_facility }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ referral.referral_number }} ·
                    {{ referral.urgency_label }} · issued
                    {{ referral.referred_at
                    }}<template v-if="referral.referred_by">
                        by {{ referral.referred_by }}</template
                    >
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="rounded-md px-2 py-1 text-xs font-medium"
                    :class="TONES[referral.status_tone]"
                    >{{ referral.status_label }}</span
                >
                <Button as-child variant="outline">
                    <a
                        :href="referral.urls.letter"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="size-4" />
                        Print letter
                    </a>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Letter</h2>
                    <dl class="grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Patient
                            </dt>
                            <dd>
                                <Link
                                    :href="referral.patient.url"
                                    class="font-medium hover:underline"
                                    >{{ referral.patient.name }}</Link
                                >
                                <span class="text-xs text-muted-foreground">
                                    · {{ referral.patient.file_number }} ·
                                    {{ referral.patient.sex
                                    }}<template
                                        v-if="referral.patient.age !== null"
                                    >
                                        · {{ referral.patient.age }}y</template
                                    ><template v-if="referral.patient.phone">
                                        · {{ referral.patient.phone }}</template
                                    >
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Referred to
                            </dt>
                            <dd>
                                {{ referral.destination_facility
                                }}<template
                                    v-if="referral.destination_department"
                                >
                                    ·
                                    {{
                                        referral.destination_department
                                    }}</template
                                >
                                <span
                                    v-if="referral.destination_contact"
                                    class="text-xs text-muted-foreground"
                                >
                                    · {{ referral.destination_contact }}</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Reason
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ referral.reason }}
                            </dd>
                        </div>
                        <div v-if="referral.diagnosis">
                            <dt class="text-xs text-muted-foreground">
                                Diagnosis
                            </dt>
                            <dd>{{ referral.diagnosis }}</dd>
                        </div>
                        <div v-if="referral.clinical_summary">
                            <dt class="text-xs text-muted-foreground">
                                Clinical summary
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ referral.clinical_summary }}
                            </dd>
                        </div>
                        <div v-if="referral.treatment_given">
                            <dt class="text-xs text-muted-foreground">
                                Treatment given
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ referral.treatment_given }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-xs text-muted-foreground">
                        <template v-if="referral.printed_at">
                            Letter last printed {{ referral.printed_at }}.
                        </template>
                        <template v-else>Letter not yet printed.</template>
                        <Link
                            v-if="referral.encounter_url"
                            :href="referral.encounter_url"
                            class="ml-1 hover:underline"
                            >Open the encounter</Link
                        >
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-1 text-sm font-semibold">
                        Feedback from the receiving facility
                    </h2>
                    <div
                        v-if="referral.feedback"
                        class="mb-3 rounded-lg bg-muted/50 p-3 text-sm"
                    >
                        <p class="whitespace-pre-line">
                            {{ referral.feedback }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ referral.feedback_at
                            }}<template v-if="referral.closed_by">
                                · recorded by {{ referral.closed_by }}</template
                            >
                        </p>
                    </div>
                    <p v-else class="mb-3 text-xs text-muted-foreground">
                        Nothing recorded yet. Enter the counter-referral slip or
                        what the patient reported.
                    </p>

                    <form
                        v-if="canChange"
                        class="grid gap-3"
                        @submit.prevent="save"
                    >
                        <div class="grid gap-1.5">
                            <Label>Mark as</Label>
                            <Select v-model="form.status">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="t in referral.transitions"
                                        :key="t.value"
                                        :value="t.value"
                                        >{{ t.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Feedback</Label>
                            <textarea
                                v-model="form.feedback"
                                :class="textareaClass"
                                rows="3"
                                placeholder="Findings, treatment and advice from the receiving facility"
                                @input="autoGrow"
                            />
                            <InputError :message="form.errors.feedback" />
                        </div>
                        <Button type="submit" :disabled="form.processing">
                            Save
                        </Button>
                    </form>
                    <p v-else class="text-xs text-muted-foreground">
                        This referral is closed.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
