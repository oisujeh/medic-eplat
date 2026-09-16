<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ShieldAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    patient: {
        id: number;
        file_number: string;
        initials: string;
        restricted_reason: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Patients', href: '/patients' },
            { title: 'Restricted record', href: '#' },
        ],
    },
});

const form = useForm({ reason: '' });

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

function submit() {
    form.post(`/patients/${props.patient.id}/break-glass`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Restricted record ${patient.file_number}`" />

    <div class="flex h-full flex-1 flex-col items-center p-4">
        <div
            class="w-full max-w-lg rounded-xl border border-border bg-card p-6"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                >
                    <ShieldAlert class="size-6" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold tracking-tight">
                        This record is restricted
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        File
                        <span class="font-mono">{{ patient.file_number }}</span>
                        is marked as sensitive<template
                            v-if="patient.restricted_reason"
                        >
                            ({{ patient.restricted_reason }})</template
                        >. To open it, say why you need to. Your name, the time
                        and your reason are written to the audit trail and
                        reviewed by the facility administrator.
                    </p>
                </div>
            </div>

            <form class="mt-6 grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="break-glass-reason">Reason for opening *</Label>
                    <textarea
                        id="break-glass-reason"
                        v-model="form.reason"
                        :class="textareaClass"
                        rows="3"
                        maxlength="500"
                        placeholder="e.g. Patient presented at emergency; treating clinician needs the chart."
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <div class="flex items-center justify-between gap-3">
                    <Button as-child variant="ghost" type="button">
                        <Link href="/patients">
                            <ArrowLeft class="size-4" />
                            Back to patients
                        </Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Open record and log reason
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
