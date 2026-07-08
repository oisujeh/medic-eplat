<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    ArrowRight,
    Check,
    PhoneCall,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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

type Entry = {
    id: number;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    note: string | null;
    queued_at: string | null;
    started_at: string | null;
    assigned_to: string | null;
    assigned_to_me: boolean;
    routed_by: string | null;
    visit_number: string | null;
    latest_vitals: Vitals | null;
    patient: {
        file_number: string;
        name: string;
        initials: string;
        sex: string;
        age: number | null;
        url: string;
    };
};

type OnwardServicePoint = {
    id: number;
    name: string;
    personnel: Array<{ id: number; name: string }>;
};

const props = defineProps<{
    servicePoint: {
        name: string;
        slug: string;
        description: string | null;
        captures_vitals: boolean;
    };
    entries: Entry[];
    seesAll: boolean;
    onwardServicePoints: OnwardServicePoint[];
    priorities: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Service Queues', href: '/queues' }],
    },
});

const completingId = ref<number | null>(null);

const completeForm = useForm({
    next_service_point_id: 'none',
    next_assigned_to: 'none',
    next_priority: 'normal',
    next_note: '',
});

// Personnel eligible for the chosen onward service point.
const onwardPersonnel = computed<Array<{ id: number; name: string }>>(
    () =>
        props.onwardServicePoints.find(
            (sp) => String(sp.id) === completeForm.next_service_point_id,
        )?.personnel ?? [],
);

watch(
    () => completeForm.next_service_point_id,
    () => {
        completeForm.next_assigned_to = 'none';
    },
);

function priorityClass(priority: string): string {
    if (priority === 'emergency')
        return 'bg-red-500/10 text-red-700 dark:text-red-400';
    if (priority === 'urgent')
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    return 'bg-muted text-muted-foreground';
}

function call(entry: Entry) {
    router.post(
        `/queue-entries/${entry.id}/call`,
        {},
        { preserveScroll: true },
    );
}

function cancel(entry: Entry) {
    router.post(
        `/queue-entries/${entry.id}/cancel`,
        {},
        { preserveScroll: true },
    );
}

function openComplete(entry: Entry) {
    completingId.value = entry.id;
    completeForm.reset();
}

function submitComplete() {
    if (completingId.value === null) return;
    completeForm
        .transform((data) => ({
            next_service_point_id:
                data.next_service_point_id === 'none'
                    ? null
                    : Number(data.next_service_point_id),
            next_assigned_to:
                data.next_assigned_to === 'none'
                    ? null
                    : Number(data.next_assigned_to),
            next_priority: data.next_priority,
            next_note: data.next_note,
        }))
        .post(`/queue-entries/${completingId.value}/complete`, {
            preserveScroll: true,
            onSuccess: () => {
                completingId.value = null;
                completeForm.reset();
            },
        });
}

// ----- Vitals & anthropometrics capture -----
const vitalsId = ref<number | null>(null);

const vitalsForm = useForm<Record<string, string>>({
    temperature_c: '',
    systolic_bp: '',
    diastolic_bp: '',
    pulse_bpm: '',
    respiratory_rate: '',
    spo2: '',
    blood_glucose: '',
    pain_score: '',
    weight_kg: '',
    height_cm: '',
    muac_cm: '',
    head_circumference_cm: '',
    notes: '',
});

// Live BMI preview from the entered weight and height.
const bmiPreview = computed<string | null>(() => {
    const w = parseFloat(vitalsForm.weight_kg);
    const h = parseFloat(vitalsForm.height_cm);
    if (!w || !h) return null;
    const m = h / 100;
    return (w / (m * m)).toFixed(1);
});

function openVitals(entry: Entry) {
    vitalsId.value = entry.id;
    vitalsForm.reset();
    vitalsForm.clearErrors();
}

function submitVitals() {
    if (vitalsId.value === null) return;
    vitalsForm
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, v === '' ? null : v]),
            ),
        )
        .post(`/queue-entries/${vitalsId.value}/vitals`, {
            preserveScroll: true,
            onSuccess: () => {
                vitalsId.value = null;
                vitalsForm.reset();
            },
        });
}

const vitalFields: Array<{ key: string; label: string; step?: string }> = [
    { key: 'temperature_c', label: 'Temp (°C)', step: '0.1' },
    { key: 'systolic_bp', label: 'Systolic (mmHg)' },
    { key: 'diastolic_bp', label: 'Diastolic (mmHg)' },
    { key: 'pulse_bpm', label: 'Pulse (bpm)' },
    { key: 'respiratory_rate', label: 'Resp. rate (/min)' },
    { key: 'spo2', label: 'SpO₂ (%)' },
    { key: 'blood_glucose', label: 'Glucose (mmol/L)', step: '0.1' },
    { key: 'pain_score', label: 'Pain (0–10)' },
];

const anthroFields: Array<{ key: string; label: string; step?: string }> = [
    { key: 'weight_kg', label: 'Weight (kg)', step: '0.01' },
    { key: 'height_cm', label: 'Height (cm)', step: '0.1' },
    { key: 'muac_cm', label: 'MUAC (cm)', step: '0.1' },
    { key: 'head_circumference_cm', label: 'Head circ. (cm)', step: '0.1' },
];
</script>

<template>
    <Head :title="`${props.servicePoint.name} — Queue`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Link
            href="/queues"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            All queues
        </Link>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ props.servicePoint.name }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ props.entries.length }} patient{{
                    props.entries.length === 1 ? '' : 's'
                }}
                in queue
                <span v-if="!props.seesAll">
                    — showing patients assigned to you and the unassigned pool</span
                >
            </p>
        </div>

        <div
            v-if="!props.entries.length"
            class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
        >
            No patients waiting. Patients routed here will appear in this queue.
        </div>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="entry in props.entries"
                :key="entry.id"
                class="rounded-xl border border-border bg-card p-4"
                :class="
                    entry.status === 'in_service'
                        ? 'border-primary/40 ring-1 ring-primary/20'
                        : ''
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                        >
                            {{ entry.patient.initials }}
                        </span>
                        <div>
                            <Link
                                :href="entry.patient.url"
                                class="font-medium hover:underline"
                            >
                                {{ entry.patient.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                <span class="font-mono">{{
                                    entry.patient.file_number
                                }}</span>
                                · {{ entry.patient.sex
                                }}{{
                                    entry.patient.age !== null
                                        ? ' · ' + entry.patient.age + 'y'
                                        : ''
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                            :class="priorityClass(entry.priority)"
                        >
                            {{ entry.priority_label }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                entry.status === 'in_service'
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ entry.status_label }}
                        </span>
                        <span
                            v-if="entry.assigned_to_me"
                            class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                        >
                            Assigned to you
                        </span>
                        <span
                            v-else-if="entry.assigned_to"
                            class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ entry.assigned_to }}
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                        >
                            Unassigned
                        </span>
                    </div>
                </div>

                <div
                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <span v-if="entry.status === 'waiting'"
                        >Waiting {{ entry.queued_at }}</span
                    >
                    <span v-else>Started {{ entry.started_at }}</span>
                    <span v-if="entry.assigned_to && entry.status === 'in_service'"
                        >Attending: {{ entry.assigned_to }}</span
                    >
                    <span v-if="entry.routed_by">Routed by {{ entry.routed_by }}</span>
                    <span v-if="entry.visit_number" class="font-mono">{{
                        entry.visit_number
                    }}</span>
                </div>

                <p
                    v-if="entry.note"
                    class="mt-2 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
                >
                    {{ entry.note }}
                </p>

                <!-- Latest recorded vitals -->
                <div v-if="entry.latest_vitals" class="mt-3">
                    <div
                        v-if="alertBadge(entry.latest_vitals.alert_level)"
                        class="mb-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="alertBadge(entry.latest_vitals.alert_level)!.class"
                    >
                        <TriangleAlert class="size-3" />
                        {{ alertBadge(entry.latest_vitals.alert_level)!.label }}:
                        {{
                            entry.latest_vitals.flags
                                .map((f) => f.label)
                                .join(', ')
                        }}
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="chip in vitalsChips(entry.latest_vitals)"
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
                </div>

                <!-- Actions -->
                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        v-if="entry.status === 'waiting'"
                        size="sm"
                        @click="call(entry)"
                    >
                        <PhoneCall class="size-4" />
                        Call
                    </Button>
                    <Button
                        v-if="
                            props.servicePoint.captures_vitals &&
                            entry.status === 'in_service' &&
                            vitalsId !== entry.id
                        "
                        variant="outline"
                        size="sm"
                        @click="openVitals(entry)"
                    >
                        <Activity class="size-4" />
                        {{ entry.latest_vitals ? 'Update vitals' : 'Record vitals' }}
                    </Button>
                    <Button
                        v-if="entry.status === 'in_service' && completingId !== entry.id"
                        size="sm"
                        @click="openComplete(entry)"
                    >
                        <Check class="size-4" />
                        Complete
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="cancel(entry)"
                    >
                        <X class="size-4" />
                        Cancel
                    </Button>
                </div>

                <!-- Vitals & anthropometrics capture panel -->
                <div
                    v-if="vitalsId === entry.id"
                    class="mt-3 rounded-lg border border-border bg-muted/30 p-3"
                >
                    <p class="mb-2 text-xs font-semibold text-muted-foreground">
                        Vitals
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            v-for="f in vitalFields"
                            :key="f.key"
                            class="grid gap-1"
                        >
                            <Label class="text-xs">{{ f.label }}</Label>
                            <Input
                                v-model="vitalsForm[f.key]"
                                type="number"
                                :step="f.step ?? '1'"
                                class="h-8 bg-background"
                            />
                        </div>
                    </div>

                    <p
                        class="mt-3 mb-2 text-xs font-semibold text-muted-foreground"
                    >
                        Anthropometrics
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            v-for="f in anthroFields"
                            :key="f.key"
                            class="grid gap-1"
                        >
                            <Label class="text-xs">{{ f.label }}</Label>
                            <Input
                                v-model="vitalsForm[f.key]"
                                type="number"
                                :step="f.step ?? '1'"
                                class="h-8 bg-background"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs">BMI (auto)</Label>
                            <div
                                class="flex h-8 items-center rounded-md border border-border bg-background px-3 text-sm"
                                :class="bmiPreview ? '' : 'text-muted-foreground'"
                            >
                                {{ bmiPreview ?? '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-1">
                        <Label class="text-xs">Notes</Label>
                        <Input
                            v-model="vitalsForm.notes"
                            placeholder="Optional observations"
                            class="h-8 bg-background"
                        />
                    </div>

                    <InputError
                        class="mt-2"
                        :message="vitalsForm.errors.temperature_c"
                    />

                    <div class="mt-3 flex gap-2">
                        <Button
                            size="sm"
                            :disabled="vitalsForm.processing"
                            @click="submitVitals"
                        >
                            <Check class="size-4" />
                            Save vitals
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="vitalsId = null"
                        >
                            Dismiss
                        </Button>
                    </div>
                </div>

                <!-- Complete + route-onward panel -->
                <div
                    v-if="completingId === entry.id"
                    class="mt-3 grid gap-3 rounded-lg border border-border bg-muted/30 p-3 sm:grid-cols-2"
                >
                    <div class="grid gap-1.5">
                        <Label>Route onward to</Label>
                        <Select v-model="completeForm.next_service_point_id">
                            <SelectTrigger class="w-full bg-background">
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
                        v-if="completeForm.next_service_point_id !== 'none'"
                        class="grid gap-1.5"
                    >
                        <Label>Priority</Label>
                        <Select v-model="completeForm.next_priority">
                            <SelectTrigger class="w-full bg-background">
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
                        v-if="completeForm.next_service_point_id !== 'none'"
                        class="grid gap-1.5 sm:col-span-2"
                    >
                        <Label>Assign to personnel</Label>
                        <Select v-model="completeForm.next_assigned_to">
                            <SelectTrigger class="w-full bg-background">
                                <SelectValue
                                    placeholder="Unassigned — anyone at this point"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >Unassigned — anyone at this point</SelectItem
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
                        v-if="completeForm.next_service_point_id !== 'none'"
                        class="grid gap-1.5 sm:col-span-2"
                    >
                        <Label>Note (optional)</Label>
                        <Input
                            v-model="completeForm.next_note"
                            placeholder="Reason for onward routing"
                            class="bg-background"
                        />
                    </div>

                    <div class="flex gap-2 sm:col-span-2">
                        <Button
                            size="sm"
                            :disabled="completeForm.processing"
                            @click="submitComplete"
                        >
                            <ArrowRight class="size-4" />
                            {{
                                completeForm.next_service_point_id === 'none'
                                    ? 'Complete'
                                    : 'Complete & route'
                            }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="completingId = null"
                        >
                            Dismiss
                        </Button>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
