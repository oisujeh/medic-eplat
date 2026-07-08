<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ExternalLink, Save } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { type Vitals, alertBadge, chipClass, vitalsChips } from '@/lib/vitals';

const props = defineProps<{
    entry: { id: number; service_point: string; visit_number: string | null };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        sex_label: string;
        age: number | null;
        url: string;
    };
    vitals: Vitals | null;
    encounter: {
        presenting_complaint: string | null;
        history: string | null;
        examination: string | null;
        diagnosis: string | null;
        plan: string | null;
        status: string;
    };
    pastEncounters: Array<{
        id: number;
        diagnosis: string | null;
        presenting_complaint: string | null;
        plan: string | null;
        clinician: string | null;
        completed_at: string | null;
    }>;
    onwardServicePoints: Array<{
        id: number;
        name: string;
        personnel: Array<{ id: number; name: string }>;
    }>;
    priorities: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clinical', href: '/clinical' },
            { title: 'Consultation', href: '#' },
        ],
    },
});

const form = useForm({
    presenting_complaint: props.encounter.presenting_complaint ?? '',
    history: props.encounter.history ?? '',
    examination: props.encounter.examination ?? '',
    diagnosis: props.encounter.diagnosis ?? '',
    plan: props.encounter.plan ?? '',
    next_service_point_id: 'none',
    next_assigned_to: 'none',
    next_priority: 'normal',
    next_note: '',
});

const onwardPersonnel = computed<Array<{ id: number; name: string }>>(
    () =>
        props.onwardServicePoints.find(
            (sp) => String(sp.id) === form.next_service_point_id,
        )?.personnel ?? [],
);

watch(
    () => form.next_service_point_id,
    () => {
        form.next_assigned_to = 'none';
    },
);

const textareaClass =
    'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

function saveDraft() {
    form.post(`/clinical/${props.entry.id}/save`, { preserveScroll: true });
}

function complete() {
    form
        .transform((data) => ({
            ...data,
            next_service_point_id:
                data.next_service_point_id === 'none'
                    ? null
                    : Number(data.next_service_point_id),
            next_assigned_to:
                data.next_assigned_to === 'none'
                    ? null
                    : Number(data.next_assigned_to),
        }))
        .post(`/clinical/${props.entry.id}/complete`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Consultation — ${props.patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4">
        <Link
            href="/clinical"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to clinical
        </Link>

        <!-- Patient header -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-card p-5"
        >
            <div class="flex items-center gap-4">
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary"
                >
                    {{ props.patient.initials }}
                </span>
                <div>
                    <h1 class="text-lg font-semibold tracking-tight">
                        {{ props.patient.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <span class="font-mono">{{
                            props.patient.file_number
                        }}</span>
                        · {{ props.patient.sex_label
                        }}{{
                            props.patient.age !== null
                                ? ' · ' + props.patient.age + 'y'
                                : ''
                        }}
                        · {{ props.entry.service_point }}
                    </p>
                </div>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="props.patient.url">
                    <ExternalLink class="size-4" />
                    Full profile
                </Link>
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <!-- Documentation -->
            <form class="flex flex-col gap-6" @submit.prevent="complete">
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold">Consultation</h2>
                    <div class="flex flex-col gap-4">
                        <div class="grid gap-1.5">
                            <Label>Presenting complaint</Label>
                            <textarea
                                v-model="form.presenting_complaint"
                                :class="textareaClass"
                                placeholder="What the patient reports…"
                            />
                            <InputError
                                :message="form.errors.presenting_complaint"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>History</Label>
                            <textarea
                                v-model="form.history"
                                :class="textareaClass"
                                placeholder="History of presenting complaint, past history…"
                            />
                            <InputError :message="form.errors.history" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Examination</Label>
                            <textarea
                                v-model="form.examination"
                                :class="textareaClass"
                                placeholder="Examination findings…"
                            />
                            <InputError :message="form.errors.examination" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Diagnosis / impression *</Label>
                            <textarea
                                v-model="form.diagnosis"
                                :class="textareaClass"
                                placeholder="Working diagnosis or impression…"
                            />
                            <InputError :message="form.errors.diagnosis" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Plan</Label>
                            <textarea
                                v-model="form.plan"
                                :class="textareaClass"
                                placeholder="Management plan, medications, investigations…"
                            />
                            <InputError :message="form.errors.plan" />
                        </div>
                    </div>
                </section>

                <!-- Disposition -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-1 text-sm font-semibold">Disposition</h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        On completion, optionally route the patient onward (e.g.
                        Pharmacy, Laboratory, Billing).
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Route onward to</Label>
                            <Select v-model="form.next_service_point_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none"
                                        >Complete only — don't route</SelectItem
                                    >
                                    <SelectItem
                                        v-for="sp in props.onwardServicePoints"
                                        :key="sp.id"
                                        :value="String(sp.id)"
                                        >{{ sp.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div
                            v-if="form.next_service_point_id !== 'none'"
                            class="grid gap-1.5"
                        >
                            <Label>Priority</Label>
                            <Select v-model="form.next_priority">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in props.priorities"
                                        :key="p.value"
                                        :value="p.value"
                                        >{{ p.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div
                            v-if="form.next_service_point_id !== 'none'"
                            class="grid gap-1.5"
                        >
                            <Label>Assign to personnel</Label>
                            <Select v-model="form.next_assigned_to">
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Unassigned — anyone at this point"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none"
                                        >Unassigned — anyone at this
                                        point</SelectItem
                                    >
                                    <SelectItem
                                        v-for="person in onwardPersonnel"
                                        :key="person.id"
                                        :value="String(person.id)"
                                        >{{ person.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div
                            v-if="form.next_service_point_id !== 'none'"
                            class="grid gap-1.5 sm:col-span-2"
                        >
                            <Label>Note (optional)</Label>
                            <Input
                                v-model="form.next_note"
                                placeholder="Reason for onward routing"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="saveDraft"
                    >
                        <Save class="size-4" />
                        Save draft
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <CheckCircle2 class="size-4" />
                        Complete consultation
                    </Button>
                </div>
            </form>

            <!-- Aside: vitals + history -->
            <aside class="flex flex-col gap-6">
                <section class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-3 flex items-center gap-2">
                        <h2 class="text-sm font-semibold">Vitals (this visit)</h2>
                        <span
                            v-if="vitals && alertBadge(vitals.alert_level)"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="alertBadge(vitals.alert_level)!.class"
                            >{{ alertBadge(vitals.alert_level)!.label }}</span
                        >
                    </div>
                    <div v-if="vitals" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="chip in vitalsChips(vitals)"
                            :key="chip.label"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs"
                            :class="chipClass(chip.level)"
                            :title="chip.reason ?? undefined"
                        >
                            <span
                                :class="
                                    chip.level === 'normal'
                                        ? 'text-muted-foreground'
                                        : 'opacity-80'
                                "
                                >{{ chip.label }}</span
                            >
                            <span class="font-medium">{{ chip.value }}</span>
                        </span>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No vitals recorded for this visit.
                    </p>
                </section>

                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Past consultations</h2>
                    <ul
                        v-if="pastEncounters.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="e in pastEncounters"
                            :key="e.id"
                            class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="text-sm font-medium">{{
                                e.diagnosis || 'No diagnosis'
                            }}</span>
                            <span
                                v-if="e.presenting_complaint"
                                class="text-xs text-muted-foreground"
                                >{{ e.presenting_complaint }}</span
                            >
                            <span class="text-xs text-muted-foreground/70">
                                {{ e.completed_at
                                }}<span v-if="e.clinician">
                                    · {{ e.clinician }}</span
                                >
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No previous consultations.
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
