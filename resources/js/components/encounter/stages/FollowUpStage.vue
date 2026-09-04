<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    followUpIntervals,
    monitoringGoals,
    useEncounterContext,
} from '@/composables/useEncounterForm';
import {
    autoGrow,
    textareaClass,
    toDatetimeLocal,
    toggleInList,
} from '@/lib/forms';
import type { Option, ServicePointOption } from '@/types/clinical';

defineProps<{
    outcomes: Option[];
    clinics: ServicePointOption[];
}>();

const { encounter, form, readOnly } = useEncounterContext();

// Picking an interval also seeds the next-appointment date.
function pickInterval(code: string) {
    const fu = form.structured.follow_up;
    fu.interval = fu.interval === code ? '' : code;

    if (!fu.interval) {
        return;
    }

    const d = new Date();
    d.setHours(9, 0, 0, 0);

    if (code === '1w') {
        d.setDate(d.getDate() + 7);
    } else if (code === '2w') {
        d.setDate(d.getDate() + 14);
    } else if (code === '1m') {
        d.setMonth(d.getMonth() + 1);
    } else if (code === '3m') {
        d.setMonth(d.getMonth() + 3);
    } else if (code === '6m') {
        d.setMonth(d.getMonth() + 6);
    }

    form.follow_up_at = toDatetimeLocal(d.toISOString());
}

const followUpForm = useForm<{
    service_point_id: string;
    reason: string;
    scheduled_start: string;
}>({
    service_point_id: '',
    reason: 'Follow-up',
    scheduled_start: '',
});

function bookFollowUp() {
    followUpForm
        .transform((d) => ({
            ...d,
            service_point_id: Number(d.service_point_id),
            scheduled_start: form.follow_up_at,
        }))
        .post(encounter.urls.follow_up, {
            preserveScroll: true,
            onSuccess: () => followUpForm.reset('service_point_id'),
            onFinish: () => followUpForm.transform((d) => d),
        });
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="mb-4 text-base font-semibold">
                Follow-up &amp; outcome
            </h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label
                        ><CalendarDays class="mr-1 inline size-3.5" />Next
                        appointment</Label
                    >
                    <Input
                        v-model="form.follow_up_at"
                        type="datetime-local"
                        :disabled="readOnly"
                    />
                    <InputError :message="form.errors.follow_up_at" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Follow-up interval</Label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="iv in followUpIntervals"
                            :key="iv.value"
                            type="button"
                            class="rounded-md border px-2.5 py-1.5 text-xs transition-colors disabled:opacity-60"
                            :class="
                                form.structured.follow_up.interval === iv.value
                                    ? 'border-primary bg-primary/10 font-medium text-primary'
                                    : 'border-border text-muted-foreground hover:bg-muted'
                            "
                            :disabled="readOnly"
                            @click="pickInterval(iv.value)"
                        >
                            {{ iv.label }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-1.5">
                <Label>Monitoring goals</Label>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label
                        v-for="g in monitoringGoals"
                        :key="g.value"
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :model-value="
                                form.structured.follow_up.monitoring_goals.includes(
                                    g.value,
                                )
                            "
                            :disabled="readOnly"
                            @update:model-value="
                                toggleInList(
                                    form.structured.follow_up.monitoring_goals,
                                    g.value,
                                )
                            "
                        />
                        {{ g.label }}
                    </label>
                </div>
            </div>

            <div class="mt-4 grid gap-1.5">
                <Label>Patient instructions</Label>
                <textarea
                    v-model="form.structured.follow_up.patient_instructions"
                    :class="textareaClass"
                    rows="3"
                    :disabled="readOnly"
                    placeholder="Take medication daily. Reduce salt intake. Return immediately if…"
                    @input="autoGrow"
                />
            </div>

            <div class="mt-4 grid gap-1.5">
                <Label>Outcome</Label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="o in outcomes"
                        :key="o.value"
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm transition-colors disabled:opacity-60"
                        :class="
                            form.outcome === o.value
                                ? 'border-primary bg-primary/10 font-medium text-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'
                        "
                        :disabled="readOnly"
                        @click="
                            form.outcome =
                                form.outcome === o.value ? '' : o.value
                        "
                    >
                        {{ o.label }}
                    </button>
                </div>
                <InputError :message="form.errors.outcome" />
                <p
                    v-if="form.outcome === 'admit'"
                    class="text-xs text-muted-foreground"
                >
                    Signing with this outcome orders an admission; the ward
                    assigns the bed.
                </p>
            </div>
        </div>

        <div
            v-if="!readOnly"
            class="rounded-xl border border-border bg-card p-5"
        >
            <h3 class="mb-1 text-sm font-semibold">
                Book follow-up appointment
            </h3>
            <p class="mb-3 text-xs text-muted-foreground">
                Create a scheduled appointment for the next-visit date set
                above.
            </p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5">
                    <Label>Clinic</Label>
                    <Select v-model="followUpForm.service_point_id">
                        <SelectTrigger class="w-56">
                            <SelectValue placeholder="Select clinic" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sp in clinics"
                                :key="sp.id"
                                :value="String(sp.id)"
                                >{{ sp.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="
                        followUpForm.processing ||
                        !form.follow_up_at ||
                        !followUpForm.service_point_id
                    "
                    @click="bookFollowUp"
                >
                    <CalendarPlus class="size-4" />
                    Book appointment
                </Button>
            </div>
            <p
                v-if="!form.follow_up_at"
                class="mt-2 text-xs text-muted-foreground"
            >
                Set a next-appointment date above first.
            </p>
            <InputError :message="followUpForm.errors.scheduled_start" />
            <InputError :message="followUpForm.errors.service_point_id" />
        </div>
    </section>
</template>
