<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, Printer, Send } from '@lucide/vue';
import { ref } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { autoGrow, textareaClass } from '@/lib/forms';
import type { Option, Referral, ReferralDraft } from '@/types/clinical';

const props = defineProps<{
    referrals: Referral[];
    draft: ReferralDraft;
    priorities: Option[];
    /** encounters.referrals.store */
    action: string;
    encounterId: number;
    readOnly: boolean;
    /** True when the outcome is "Referred" and no referral exists yet. */
    needed: boolean;
}>();

const open = ref(false);

const form = useForm({
    destination_facility: '',
    destination_department: '',
    destination_contact: '',
    urgency: 'normal',
    reason: '',
    diagnosis: props.draft.diagnosis ?? '',
    clinical_summary: props.draft.clinical_summary ?? '',
    treatment_given: props.draft.treatment_given ?? '',
});

function submit() {
    form.transform((d) => ({
        ...d,
        destination_department: d.destination_department || null,
        destination_contact: d.destination_contact || null,
        diagnosis: d.diagnosis || null,
        clinical_summary: d.clinical_summary || null,
        treatment_given: d.treatment_given || null,
    })).post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset(
                'destination_facility',
                'destination_department',
                'destination_contact',
                'reason',
            );
        },
        onFinish: () => form.transform((d) => d),
    });
}

const TONES: Record<string, string> = {
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    red: 'bg-red-500/10 text-red-700 dark:text-red-400',
    muted: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <div
        class="rounded-xl border bg-card p-5"
        :class="needed ? 'border-amber-500/40' : 'border-border'"
    >
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold">
                    Referral to another facility
                </h3>
                <p class="text-xs text-muted-foreground">
                    <template v-if="needed">
                        The outcome is set to Referred. Issue the referral and
                        print the letter before signing.
                    </template>
                    <template v-else>
                        Issue a referral letter for the patient to take along;
                        the register tracks whether they were seen.
                    </template>
                </p>
            </div>
            <Button
                v-if="!readOnly"
                type="button"
                :variant="needed ? 'default' : 'outline'"
                size="sm"
                @click="open = true"
            >
                <Send class="size-4" />
                Refer patient
            </Button>
        </div>

        <ul v-if="referrals.length" class="divide-y divide-border/60">
            <li
                v-for="r in referrals"
                :key="r.id"
                class="flex flex-wrap items-center gap-3 py-2 text-sm"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        {{ r.destination_facility
                        }}<span
                            v-if="r.destination_department"
                            class="font-normal text-muted-foreground"
                        >
                            · {{ r.destination_department }}</span
                        >
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ r.referral_number }} · {{ r.urgency_label }} ·
                        {{ r.referred_at
                        }}<template v-if="r.encounter_id !== encounterId">
                            · earlier encounter</template
                        >
                    </p>
                </div>
                <span
                    class="rounded-md px-1.5 py-0.5 text-xs font-medium"
                    :class="TONES[r.status_tone]"
                    >{{ r.status_label }}</span
                >
                <a
                    :href="r.urls.letter"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                    <Printer class="size-3.5" />
                    Letter
                </a>
                <Link
                    :href="r.urls.show"
                    class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                    <ExternalLink class="size-3.5" />
                    Open
                </Link>
            </li>
        </ul>
        <p v-else class="text-xs text-muted-foreground">
            No referrals for this patient.
        </p>

        <Dialog v-model:open="open">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Refer patient</DialogTitle>
                    <DialogDescription>
                        The diagnosis, summary and treatment are pre-filled from
                        this encounter. Edit them so the receiving clinician
                        sees what matters.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Referred to *</Label>
                        <Input
                            v-model="form.destination_facility"
                            placeholder="e.g. Lagos University Teaching Hospital"
                        />
                        <InputError
                            :message="form.errors.destination_facility"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Department or specialty</Label>
                        <Input
                            v-model="form.destination_department"
                            placeholder="e.g. Cardiology"
                        />
                        <InputError
                            :message="form.errors.destination_department"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Contact person or phone</Label>
                        <Input
                            v-model="form.destination_contact"
                            placeholder="e.g. Dr Bello, 0803…"
                        />
                        <InputError
                            :message="form.errors.destination_contact"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Urgency *</Label>
                        <Select v-model="form.urgency">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="p in priorities"
                                    :key="p.value"
                                    :value="p.value"
                                    >{{ p.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.urgency" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Diagnosis</Label>
                        <Input v-model="form.diagnosis" />
                        <InputError :message="form.errors.diagnosis" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Reason for referral *</Label>
                        <textarea
                            v-model="form.reason"
                            :class="textareaClass"
                            rows="2"
                            placeholder="What you need the receiving facility to do"
                            @input="autoGrow"
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Clinical summary</Label>
                        <textarea
                            v-model="form.clinical_summary"
                            :class="textareaClass"
                            rows="4"
                            @input="autoGrow"
                        />
                        <InputError :message="form.errors.clinical_summary" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Treatment given</Label>
                        <textarea
                            v-model="form.treatment_given"
                            :class="textareaClass"
                            rows="2"
                            @input="autoGrow"
                        />
                        <InputError :message="form.errors.treatment_given" />
                    </div>
                    <div
                        class="flex items-center justify-end gap-2 sm:col-span-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Send class="size-4" />
                            Issue referral
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
