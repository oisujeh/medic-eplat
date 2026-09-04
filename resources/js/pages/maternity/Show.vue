<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Baby,
    BedSingle,
    HeartPulse,
    Pencil,
    Plus,
    UserPlus,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Spinner } from '@/components/ui/spinner';

type Option = { value: string; label: string };

type BirthRow = {
    id: number;
    birth_order: number;
    outcome: string;
    outcome_label: string;
    is_live: boolean;
    sex: string;
    weight_grams: number | null;
    low_birth_weight: boolean;
    apgar_1: number | null;
    apgar_5: number | null;
    resuscitated: boolean;
    breastfed_within_hour: boolean;
    bcg_given: boolean;
    opv0_given: boolean;
    hepb0_given: boolean;
    condition: string | null;
    notes: string | null;
    newborn: { file_number: string; url: string } | null;
};

const props = defineProps<{
    pregnancy: {
        id: number;
        pregnancy_number: string;
        status: string;
        status_label: string;
        tone: string;
        is_active: boolean;
        lmp: string | null;
        lmp_label: string | null;
        edd: string | null;
        edd_label: string | null;
        edd_diff: string | null;
        ga_weeks: number | null;
        overdue: boolean;
        gravida: number | null;
        para: number | null;
        booking_date: string | null;
        booking_date_label: string | null;
        booked_by: string | null;
        risk_factors: string[];
        notes: string | null;
        outcome_note: string | null;
        closed_at: string | null;
        closed_by: string | null;
    };
    patient: {
        id: number;
        name: string;
        initials: string;
        file_number: string;
        age: number | null;
        phone: string | null;
        coverage_label: string;
        url: string;
    };
    admission: {
        ward: string | null;
        bed: string | null;
        status_label: string;
        url: string;
    } | null;
    ancVisits: Array<{
        id: number;
        date: string | null;
        ga_weeks: number | null;
        fundal_height_cm: number | null;
        fetal_heart_rate: number | null;
        presentation: string | null;
        assessment: string | null;
        by: string | null;
    }>;
    delivery: {
        id: number;
        delivered_at: string;
        mode: string;
        mode_label: string;
        is_caesarean: boolean;
        labour_onset: string | null;
        gestational_age_weeks: number | null;
        place: string;
        attendant: string | null;
        complications: string[];
        blood_loss_ml: number | null;
        maternal_outcome: string;
        maternal_outcome_label: string;
        notes: string | null;
        recorded_by: string | null;
        admission_url: string | null;
        births: BirthRow[];
    } | null;
    options: {
        riskFactors: string[];
        modes: Option[];
        labourOnsets: Option[];
        places: Option[];
        complications: string[];
        maternalOutcomes: Option[];
        birthOutcomes: Option[];
        conditions: Option[];
        attendants: Array<{ id: number; name: string }>;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Maternity', href: '/maternity' },
            { title: 'Pregnancy', href: '#' },
        ],
    },
});

const page = usePage();
const serviceError = computed(
    () =>
        (page.props.errors as Record<string, string> | undefined)?.status ??
        (page.props.errors as Record<string, string> | undefined)?.birth,
);

function toneClass(tone: string): string {
    const map: Record<string, string> = {
        blue: 'bg-primary/10 text-primary',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

// --- Edit booking ---
const editOpen = ref(false);
const editForm = useForm({
    lmp: props.pregnancy.lmp ?? '',
    edd: props.pregnancy.edd ?? '',
    gravida: props.pregnancy.gravida ?? ('' as string | number),
    para: props.pregnancy.para ?? ('' as string | number),
    booking_date: props.pregnancy.booking_date ?? '',
    risk_factors: [...props.pregnancy.risk_factors],
    notes: props.pregnancy.notes ?? '',
});

function toggleRisk(risk: string, on: boolean) {
    editForm.risk_factors = on
        ? [...new Set([...editForm.risk_factors, risk])]
        : editForm.risk_factors.filter((r) => r !== risk);
}

function saveEdit() {
    editForm
        .transform((data) => ({
            ...data,
            lmp: data.lmp || null,
            edd: data.edd || null,
            gravida: data.gravida === '' ? null : Number(data.gravida),
            para: data.para === '' ? null : Number(data.para),
            booking_date: data.booking_date || null,
        }))
        .patch(`/maternity/${props.pregnancy.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        });
}

// --- Close as loss ---
const closeOpen = ref(false);
const closeForm = useForm({ outcome_note: '' });

function submitClose() {
    closeForm.post(`/maternity/${props.pregnancy.id}/close`, {
        preserveScroll: true,
        onSuccess: () => {
            closeOpen.value = false;
        },
    });
}

// --- Record delivery ---
type BabyForm = {
    outcome: string;
    sex: string;
    weight_grams: string | number;
    apgar_1: string | number;
    apgar_5: string | number;
    resuscitated: boolean;
    breastfed_within_hour: boolean;
    bcg_given: boolean;
    opv0_given: boolean;
    hepb0_given: boolean;
    condition: string;
    notes: string;
};

const blankBaby = (): BabyForm => ({
    outcome: 'live',
    sex: '',
    weight_grams: '',
    apgar_1: '',
    apgar_5: '',
    resuscitated: false,
    breastfed_within_hour: false,
    bcg_given: false,
    opv0_given: false,
    hepb0_given: false,
    condition: 'well',
    notes: '',
});

const deliverOpen = ref(false);
const deliveryForm = useForm({
    delivered_at: '',
    mode: 'svd',
    labour_onset: 'spontaneous',
    gestational_age_weeks: props.pregnancy.ga_weeks ?? ('' as string | number),
    place: 'facility',
    attendant_id: '',
    complications: [] as string[],
    blood_loss_ml: '' as string | number,
    maternal_outcome: 'well',
    notes: '',
    births: [blankBaby()] as BabyForm[],
});

function toggleComplication(c: string, on: boolean) {
    deliveryForm.complications = on
        ? [...new Set([...deliveryForm.complications, c])]
        : deliveryForm.complications.filter((x) => x !== c);
}

function addBaby() {
    if (deliveryForm.births.length < 5) {
        deliveryForm.births = [...deliveryForm.births, blankBaby()];
    }
}

function removeBaby(index: number) {
    if (deliveryForm.births.length > 1) {
        deliveryForm.births = deliveryForm.births.filter((_, i) => i !== index);
    }
}

function babyError(index: number, field: string): string | undefined {
    return (deliveryForm.errors as Record<string, string>)[
        `births.${index}.${field}`
    ];
}

function submitDelivery() {
    deliveryForm
        .transform((data) => ({
            ...data,
            gestational_age_weeks:
                data.gestational_age_weeks === ''
                    ? null
                    : Number(data.gestational_age_weeks),
            attendant_id: data.attendant_id ? Number(data.attendant_id) : null,
            blood_loss_ml:
                data.blood_loss_ml === '' ? null : Number(data.blood_loss_ml),
            births: data.births.map((b) => ({
                ...b,
                weight_grams:
                    b.weight_grams === '' ? null : Number(b.weight_grams),
                apgar_1: b.apgar_1 === '' ? null : Number(b.apgar_1),
                apgar_5: b.apgar_5 === '' ? null : Number(b.apgar_5),
                condition: b.outcome === 'live' ? b.condition || null : null,
                notes: b.notes || null,
            })),
        }))
        .post(`/maternity/${props.pregnancy.id}/delivery`, {
            preserveScroll: true,
            onSuccess: () => {
                deliverOpen.value = false;
            },
        });
}

// --- Register newborn ---
const registering = ref<number | null>(null);
const registerForm = useForm({});

function registerNewborn(birth: BirthRow) {
    registering.value = birth.id;
    registerForm.post(`/maternity/births/${birth.id}/register`, {
        preserveScroll: true,
        onFinish: () => {
            registering.value = null;
        },
    });
}

function kg(grams: number | null): string {
    return grams === null ? '—' : (grams / 1000).toFixed(2) + ' kg';
}
</script>

<template>
    <Head :title="`${patient.name} · ${pregnancy.pregnancy_number}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link href="/maternity" aria-label="Back to maternity">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-pink-500/10 text-sm font-semibold text-pink-700"
                    >{{ patient.initials }}</span
                >
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ patient.name }}
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        <span class="font-mono">{{ patient.file_number }}</span
                        >{{
                            patient.age !== null
                                ? ' · ' + patient.age + 'y'
                                : ''
                        }}
                        ·
                        {{ patient.coverage_label }}
                        <span v-if="patient.phone"> · {{ patient.phone }}</span>
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="toneClass(pregnancy.tone)"
                            >{{ pregnancy.status_label }}</span
                        >
                        <span class="font-mono text-xs text-muted-foreground">{{
                            pregnancy.pregnancy_number
                        }}</span>
                        <span
                            v-if="
                                pregnancy.is_active &&
                                pregnancy.ga_weeks !== null
                            "
                            class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{ pregnancy.ga_weeks }} weeks</span
                        >
                        <span
                            v-if="pregnancy.overdue"
                            class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"
                        >
                            <AlertTriangle class="size-3" />
                            Past EDD
                        </span>
                        <Link
                            v-if="admission"
                            :href="admission.url"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary hover:underline"
                        >
                            <BedSingle class="size-3" />
                            {{ admission.ward }} ·
                            {{ admission.bed ?? admission.status_label }}
                        </Link>
                        <Link
                            :href="patient.url"
                            class="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            >Patient profile</Link
                        >
                    </div>
                </div>
            </div>

            <div v-if="pregnancy.is_active" class="flex flex-wrap gap-2">
                <Button variant="outline" @click="closeOpen = true"
                    >Close as loss</Button
                >
                <Button variant="outline" @click="editOpen = true">
                    <Pencil class="size-4" />
                    Edit booking
                </Button>
                <Button @click="deliverOpen = true">
                    <Baby class="size-4" />
                    Record delivery
                </Button>
            </div>
        </div>

        <InputError v-if="serviceError" :message="serviceError" />

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- Delivery -->
                <section
                    v-if="delivery"
                    class="rounded-xl border border-emerald-500/30 bg-card p-5"
                >
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <Baby class="size-4 text-emerald-600" />
                        Delivery
                    </h2>
                    <dl class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Delivered
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ delivery.delivered_at }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Mode</dt>
                            <dd class="mt-0.5 text-sm">
                                {{ delivery.mode_label }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Gestation
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{
                                    delivery.gestational_age_weeks
                                        ? delivery.gestational_age_weeks +
                                          ' weeks'
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Labour
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ delivery.labour_onset ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Place</dt>
                            <dd class="mt-0.5 text-sm">{{ delivery.place }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Attendant
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ delivery.attendant ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Blood loss
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{
                                    delivery.blood_loss_ml !== null
                                        ? delivery.blood_loss_ml + ' ml'
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Mother
                            </dt>
                            <dd
                                class="mt-0.5 text-sm"
                                :class="{
                                    'font-medium text-red-600':
                                        delivery.maternal_outcome ===
                                        'deceased',
                                }"
                            >
                                {{ delivery.maternal_outcome_label }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Complications
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{
                                    delivery.complications.length
                                        ? delivery.complications.join(', ')
                                        : 'None'
                                }}
                            </dd>
                        </div>
                        <div v-if="delivery.notes" class="sm:col-span-3">
                            <dt class="text-xs text-muted-foreground">Notes</dt>
                            <dd class="mt-0.5 text-sm whitespace-pre-line">
                                {{ delivery.notes }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Recorded by {{ delivery.recorded_by ?? '—' }}
                        <Link
                            v-if="delivery.admission_url"
                            :href="delivery.admission_url"
                            class="underline-offset-4 hover:underline"
                        >
                            · during admission</Link
                        >
                    </p>

                    <h3 class="mt-5 mb-2 text-sm font-semibold">Babies</h3>
                    <div
                        class="overflow-x-auto rounded-lg border border-border"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-3 py-2 font-medium">#</th>
                                    <th class="px-3 py-2 font-medium">
                                        Outcome
                                    </th>
                                    <th class="px-3 py-2 font-medium">Sex</th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Weight
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Apgar 1 / 5
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        At birth
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Condition
                                    </th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="b in delivery.births" :key="b.id">
                                    <td class="px-3 py-2">
                                        {{ b.birth_order }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span
                                            :class="
                                                b.is_live
                                                    ? ''
                                                    : 'font-medium text-red-600'
                                            "
                                            >{{ b.outcome_label }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-2">{{ b.sex }}</td>
                                    <td
                                        class="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                                    >
                                        {{ kg(b.weight_grams) }}
                                        <span
                                            v-if="b.low_birth_weight"
                                            class="ml-1 text-xs text-amber-600"
                                            >LBW</span
                                        >
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{ b.apgar_1 ?? '—' }} /
                                        {{ b.apgar_5 ?? '—' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-xs text-muted-foreground"
                                    >
                                        <template v-if="b.is_live">
                                            {{
                                                [
                                                    b.resuscitated
                                                        ? 'Resuscitated'
                                                        : null,
                                                    b.breastfed_within_hour
                                                        ? 'Breastfed < 1h'
                                                        : null,
                                                    b.bcg_given ? 'BCG' : null,
                                                    b.opv0_given
                                                        ? 'OPV0'
                                                        : null,
                                                    b.hepb0_given
                                                        ? 'HepB0'
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'
                                            }}
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ b.condition ?? '—' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right whitespace-nowrap"
                                    >
                                        <Button
                                            v-if="b.newborn"
                                            as-child
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link :href="b.newborn.url">{{
                                                b.newborn.file_number
                                            }}</Link>
                                        </Button>
                                        <Button
                                            v-else-if="b.is_live"
                                            size="sm"
                                            variant="outline"
                                            :disabled="registering === b.id"
                                            @click="registerNewborn(b)"
                                        >
                                            <Spinner
                                                v-if="registering === b.id"
                                            />
                                            <UserPlus v-else class="size-3.5" />
                                            Register baby
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    v-else-if="pregnancy.status === 'lost'"
                    class="rounded-xl border border-border bg-muted/40 p-5 text-sm"
                >
                    <p class="font-medium">Pregnancy closed without delivery</p>
                    <p class="mt-1">{{ pregnancy.outcome_note }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ pregnancy.closed_at }} by
                        {{ pregnancy.closed_by ?? '—' }}
                    </p>
                </section>

                <!-- ANC visits -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <HeartPulse class="size-4 text-muted-foreground" />
                        Antenatal visits
                        <span class="font-normal text-muted-foreground"
                            >({{ ancVisits.length }})</span
                        >
                    </h2>
                    <p
                        v-if="!ancVisits.length"
                        class="text-sm text-muted-foreground"
                    >
                        No ANC visits recorded since booking. Visits are
                        documented at the Antenatal Care service point under
                        Nursing.
                    </p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-border text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-3 py-2 font-medium">Date</th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        GA
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Fundal height
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        FHR
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Presentation
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Assessment
                                    </th>
                                    <th class="px-3 py-2 font-medium">By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="v in ancVisits" :key="v.id">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ v.date }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{
                                            v.ga_weeks !== null
                                                ? v.ga_weeks + ' wks'
                                                : '—'
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{
                                            v.fundal_height_cm !== null
                                                ? v.fundal_height_cm + ' cm'
                                                : '—'
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{ v.fetal_heart_rate ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ v.presentation ?? '—' }}
                                    </td>
                                    <td class="max-w-64 px-3 py-2">
                                        <p
                                            class="truncate"
                                            :title="v.assessment ?? ''"
                                        >
                                            {{ v.assessment ?? '—' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ v.by ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-6">
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-sm font-semibold">Booking</h2>
                    <dl class="grid grid-cols-2 gap-y-2 text-sm">
                        <dt class="text-muted-foreground">LMP</dt>
                        <dd class="text-right">
                            {{ pregnancy.lmp_label ?? '—' }}
                        </dd>
                        <dt class="text-muted-foreground">EDD</dt>
                        <dd
                            class="text-right"
                            :class="{
                                'font-medium text-red-600': pregnancy.overdue,
                            }"
                        >
                            {{ pregnancy.edd_label ?? '—' }}
                        </dd>
                        <dt class="text-muted-foreground">Gestation</dt>
                        <dd class="text-right">
                            {{
                                pregnancy.ga_weeks !== null
                                    ? pregnancy.ga_weeks + ' weeks'
                                    : '—'
                            }}
                        </dd>
                        <dt class="text-muted-foreground">Gravida / Para</dt>
                        <dd class="text-right">
                            {{ pregnancy.gravida ?? '—' }} /
                            {{ pregnancy.para ?? '—' }}
                        </dd>
                        <dt class="text-muted-foreground">Booked</dt>
                        <dd class="text-right">
                            {{ pregnancy.booking_date_label ?? '—' }}
                        </dd>
                        <dt class="text-muted-foreground">Booked by</dt>
                        <dd class="text-right">
                            {{ pregnancy.booked_by ?? '—' }}
                        </dd>
                    </dl>
                    <p
                        v-if="pregnancy.notes"
                        class="mt-3 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ pregnancy.notes }}
                    </p>
                </section>

                <section class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <AlertTriangle
                            class="size-4"
                            :class="
                                pregnancy.risk_factors.length
                                    ? 'text-red-600'
                                    : 'text-muted-foreground'
                            "
                        />
                        Risk factors
                    </h2>
                    <ul
                        v-if="pregnancy.risk_factors.length"
                        class="flex flex-wrap gap-1.5"
                    >
                        <li
                            v-for="r in pregnancy.risk_factors"
                            :key="r"
                            class="rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"
                        >
                            {{ r }}
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        None recorded.
                    </p>
                </section>
            </aside>
        </div>

        <!-- Edit booking dialog -->
        <Dialog v-model:open="editOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit booking</DialogTitle>
                    <DialogDescription>
                        Changing the LMP recalculates the EDD unless an EDD is
                        entered.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="saveEdit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="edit-lmp">Last menstrual period</Label>
                            <Input
                                id="edit-lmp"
                                v-model="editForm.lmp"
                                type="date"
                            />
                            <InputError :message="editForm.errors.lmp" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="edit-edd">Expected delivery</Label>
                            <Input
                                id="edit-edd"
                                v-model="editForm.edd"
                                type="date"
                            />
                            <InputError :message="editForm.errors.edd" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="edit-gravida">Gravida</Label>
                            <Input
                                id="edit-gravida"
                                v-model="editForm.gravida"
                                type="number"
                                min="1"
                                max="20"
                            />
                            <InputError :message="editForm.errors.gravida" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="edit-para">Para</Label>
                            <Input
                                id="edit-para"
                                v-model="editForm.para"
                                type="number"
                                min="0"
                                max="20"
                            />
                            <InputError :message="editForm.errors.para" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="edit-booking">Booking date</Label>
                            <Input
                                id="edit-booking"
                                v-model="editForm.booking_date"
                                type="date"
                            />
                            <InputError
                                :message="editForm.errors.booking_date"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Risk factors</Label>
                        <div class="grid gap-1.5 sm:grid-cols-2">
                            <Label
                                v-for="risk in options.riskFactors"
                                :key="risk"
                                class="flex items-center gap-2 text-sm font-normal"
                            >
                                <Checkbox
                                    :model-value="
                                        editForm.risk_factors.includes(risk)
                                    "
                                    @update:model-value="
                                        (v) => toggleRisk(risk, v === true)
                                    "
                                />
                                {{ risk }}
                            </Label>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-notes">Notes</Label>
                        <textarea
                            id="edit-notes"
                            v-model="editForm.notes"
                            :class="textareaClass"
                            rows="2"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="editForm.processing">
                            <Spinner v-if="editForm.processing" />
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Close as loss dialog -->
        <Dialog v-model:open="closeOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Close pregnancy without delivery</DialogTitle>
                    <DialogDescription>
                        For a miscarriage, abortion, ectopic pregnancy or a
                        woman lost to follow-up. Record what happened.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitClose">
                    <div class="grid gap-1.5">
                        <Label for="close-note">Outcome *</Label>
                        <textarea
                            id="close-note"
                            v-model="closeForm.outcome_note"
                            :class="textareaClass"
                            rows="3"
                        ></textarea>
                        <InputError :message="closeForm.errors.outcome_note" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="closeOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="closeForm.processing"
                        >
                            <Spinner v-if="closeForm.processing" />
                            Close pregnancy
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Record delivery dialog -->
        <Dialog v-model:open="deliverOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record delivery</DialogTitle>
                    <DialogDescription>
                        Closes the pregnancy and adds each baby to the birth
                        register. Live-born babies can be registered as patients
                        afterwards.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-5" @submit.prevent="submitDelivery">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="delivered_at">Delivered at *</Label>
                            <Input
                                id="delivered_at"
                                v-model="deliveryForm.delivered_at"
                                type="datetime-local"
                            />
                            <InputError
                                :message="deliveryForm.errors.delivered_at"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Mode of delivery *</Label>
                            <Select v-model="deliveryForm.mode">
                                <SelectTrigger class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in options.modes"
                                        :key="m.value"
                                        :value="m.value"
                                        >{{ m.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="deliveryForm.errors.mode" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Onset of labour</Label>
                            <Select v-model="deliveryForm.labour_onset">
                                <SelectTrigger class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="o in options.labourOnsets"
                                        :key="o.value"
                                        :value="o.value"
                                        >{{ o.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="ga">Gestation (weeks)</Label>
                            <Input
                                id="ga"
                                v-model="deliveryForm.gestational_age_weeks"
                                type="number"
                                min="20"
                                max="45"
                            />
                            <InputError
                                :message="
                                    deliveryForm.errors.gestational_age_weeks
                                "
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Place</Label>
                            <Select v-model="deliveryForm.place">
                                <SelectTrigger class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in options.places"
                                        :key="p.value"
                                        :value="p.value"
                                        >{{ p.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Attendant</Label>
                            <Select v-model="deliveryForm.attendant_id">
                                <SelectTrigger class="w-full"
                                    ><SelectValue
                                        placeholder="Who conducted the delivery"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="a in options.attendants"
                                        :key="a.id"
                                        :value="String(a.id)"
                                        >{{ a.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="blood_loss">Blood loss (ml)</Label>
                            <Input
                                id="blood_loss"
                                v-model="deliveryForm.blood_loss_ml"
                                type="number"
                                min="0"
                                max="5000"
                            />
                            <InputError
                                :message="deliveryForm.errors.blood_loss_ml"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Mother's condition *</Label>
                            <Select v-model="deliveryForm.maternal_outcome">
                                <SelectTrigger class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="o in options.maternalOutcomes"
                                        :key="o.value"
                                        :value="o.value"
                                        >{{ o.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="deliveryForm.errors.maternal_outcome"
                            />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Complications</Label>
                        <div class="grid gap-1.5 sm:grid-cols-2">
                            <Label
                                v-for="c in options.complications"
                                :key="c"
                                class="flex items-center gap-2 text-sm font-normal"
                            >
                                <Checkbox
                                    :model-value="
                                        deliveryForm.complications.includes(c)
                                    "
                                    @update:model-value="
                                        (v) => toggleComplication(c, v === true)
                                    "
                                />
                                {{ c }}
                            </Label>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="flex items-center justify-between">
                            <Label>Babies *</Label>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="deliveryForm.births.length >= 5"
                                @click="addBaby"
                            >
                                <Plus class="size-3.5" />
                                Add baby
                            </Button>
                        </div>
                        <InputError
                            :message="
                                (deliveryForm.errors as Record<string, string>)
                                    .births
                            "
                        />
                        <div
                            v-for="(baby, i) in deliveryForm.births"
                            :key="i"
                            class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-3"
                        >
                            <div
                                class="flex items-center justify-between sm:col-span-3"
                            >
                                <p class="text-sm font-medium">
                                    Baby {{ i + 1 }}
                                </p>
                                <button
                                    v-if="deliveryForm.births.length > 1"
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground"
                                    aria-label="Remove baby"
                                    @click="removeBaby(i)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Outcome *</Label>
                                <Select v-model="baby.outcome">
                                    <SelectTrigger class="w-full"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="o in options.birthOutcomes"
                                            :key="o.value"
                                            :value="o.value"
                                            >{{ o.label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="babyError(i, 'outcome')"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Sex *</Label>
                                <Select v-model="baby.sex">
                                    <SelectTrigger class="w-full"
                                        ><SelectValue placeholder="Select"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="M">Male</SelectItem>
                                        <SelectItem value="F"
                                            >Female</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError :message="babyError(i, 'sex')" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Weight (g)</Label>
                                <Input
                                    v-model="baby.weight_grams"
                                    type="number"
                                    min="300"
                                    max="7000"
                                />
                                <InputError
                                    :message="babyError(i, 'weight_grams')"
                                />
                            </div>
                            <template v-if="baby.outcome === 'live'">
                                <div class="grid gap-1.5">
                                    <Label>Apgar at 1 min</Label>
                                    <Input
                                        v-model="baby.apgar_1"
                                        type="number"
                                        min="0"
                                        max="10"
                                    />
                                    <InputError
                                        :message="babyError(i, 'apgar_1')"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Apgar at 5 min</Label>
                                    <Input
                                        v-model="baby.apgar_5"
                                        type="number"
                                        min="0"
                                        max="10"
                                    />
                                    <InputError
                                        :message="babyError(i, 'apgar_5')"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Condition</Label>
                                    <Select v-model="baby.condition">
                                        <SelectTrigger class="w-full"
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="c in options.conditions"
                                                :key="c.value"
                                                :value="c.value"
                                                >{{ c.label }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div
                                    class="flex flex-wrap gap-x-4 gap-y-1.5 sm:col-span-3"
                                >
                                    <Label
                                        class="flex items-center gap-2 text-sm font-normal"
                                    >
                                        <Checkbox v-model="baby.resuscitated" />
                                        Resuscitated
                                    </Label>
                                    <Label
                                        class="flex items-center gap-2 text-sm font-normal"
                                    >
                                        <Checkbox
                                            v-model="baby.breastfed_within_hour"
                                        />
                                        Breastfed within 1 hour
                                    </Label>
                                    <Label
                                        class="flex items-center gap-2 text-sm font-normal"
                                    >
                                        <Checkbox v-model="baby.bcg_given" />
                                        BCG given
                                    </Label>
                                    <Label
                                        class="flex items-center gap-2 text-sm font-normal"
                                    >
                                        <Checkbox v-model="baby.opv0_given" />
                                        OPV0 given
                                    </Label>
                                    <Label
                                        class="flex items-center gap-2 text-sm font-normal"
                                    >
                                        <Checkbox v-model="baby.hepb0_given" />
                                        HepB0 given
                                    </Label>
                                </div>
                            </template>
                            <div class="grid gap-1.5 sm:col-span-3">
                                <Label>Notes</Label>
                                <Input
                                    v-model="baby.notes"
                                    placeholder="Optional"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="delivery-notes">Delivery notes</Label>
                        <textarea
                            id="delivery-notes"
                            v-model="deliveryForm.notes"
                            :class="textareaClass"
                            rows="2"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="deliverOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="deliveryForm.processing"
                        >
                            <Spinner v-if="deliveryForm.processing" />
                            <Baby v-else class="size-4" />
                            Record delivery
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
