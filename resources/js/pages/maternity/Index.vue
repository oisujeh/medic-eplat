<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Baby, Plus, Search } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';

type PatientCard = {
    id: number;
    name: string;
    initials: string;
    file_number: string;
    sex: string;
    age: number | null;
    url: string;
    has_active_pregnancy?: boolean;
};

type PregnancyRow = {
    id: number;
    pregnancy_number: string;
    patient: PatientCard;
    gravida: number | null;
    para: number | null;
    edd: string | null;
    edd_diff: string | null;
    ga_weeks: number | null;
    overdue: boolean;
    due_soon: boolean;
    booking_date: string | null;
    risk_factors: string[];
    anc_visits: number;
    last_anc: string | null;
    url: string;
};

const props = defineProps<{
    pregnancies: PregnancyRow[];
    stats: {
        active: number;
        due_soon: number;
        overdue: number;
        high_risk: number;
        deliveries_month: number;
        live_births_month: number;
    };
    recentDeliveries: Array<{
        id: number;
        mother: string;
        file_number: string;
        delivered_at: string;
        mode: string;
        babies: number;
        live: number;
        maternal_outcome: string;
        url: string;
    }>;
    riskFactors: string[];
    preselected: PatientCard | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Maternity', href: '/maternity' }],
    },
});

// --- Book pregnancy dialog ---
const bookOpen = ref(false);
const patientQuery = ref('');
const results = ref<PatientCard[]>([]);
const selected = ref<PatientCard | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(patientQuery, (q) => {
    clearTimeout(timer);

    if (q.trim().length < 2) {
        results.value = [];

        return;
    }

    timer = setTimeout(async () => {
        const res = await fetch(
            `/maternity/patient-search?q=${encodeURIComponent(q)}`,
            { headers: { Accept: 'application/json' } },
        );
        results.value = (await res.json()).patients ?? [];
    }, 250);
});

const form = useForm({
    patient_id: '' as string | number,
    lmp: '',
    edd: '',
    gravida: '' as string | number,
    para: '' as string | number,
    booking_date: new Date().toISOString().slice(0, 10),
    risk_factors: [] as string[],
    notes: '',
});

function choose(p: PatientCard) {
    selected.value = p;
    form.patient_id = p.id;
    results.value = [];
    patientQuery.value = '';
}

function openBook(patient: PatientCard | null = null) {
    form.reset();
    form.clearErrors();
    selected.value = null;
    patientQuery.value = '';

    if (patient) {
        choose(patient);
    }

    bookOpen.value = true;
}

function toggleRisk(risk: string, on: boolean) {
    form.risk_factors = on
        ? [...new Set([...form.risk_factors, risk])]
        : form.risk_factors.filter((r) => r !== risk);
}

function submit() {
    form.transform((data) => ({
        ...data,
        lmp: data.lmp || null,
        edd: data.edd || null,
        gravida: data.gravida === '' ? null : Number(data.gravida),
        para: data.para === '' ? null : Number(data.para),
        booking_date: data.booking_date || null,
    })).post('/maternity', {
        onSuccess: () => {
            bookOpen.value = false;
        },
    });
}

onMounted(() => {
    if (props.preselected) {
        openBook(props.preselected);
    }
});
</script>

<template>
    <Head title="Maternity" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Maternity</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    The antenatal register, women due for delivery, and the
                    deliveries and births recorded on the ward.
                </p>
            </div>
            <Button @click="openBook()">
                <Plus class="size-4" />
                Book pregnancy
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Antenatal</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.active }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Due in 30 days</p>
                <p class="mt-1 text-2xl font-semibold text-amber-600">
                    {{ stats.due_soon }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Past EDD</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="stats.overdue ? 'text-red-600' : ''"
                >
                    {{ stats.overdue }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">High risk</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.high_risk }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">
                    Deliveries this month
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.deliveries_month }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">
                    Live births this month
                </p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">
                    {{ stats.live_births_month }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <section class="min-w-0">
                <h2 class="mb-3 text-sm font-semibold">
                    Antenatal register
                    <span class="font-normal text-muted-foreground"
                        >({{ pregnancies.length }})</span
                    >
                </h2>
                <div
                    v-if="!pregnancies.length"
                    class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
                >
                    No pregnancies under antenatal care. Book a woman to start
                    her record.
                </div>
                <div
                    v-else
                    class="overflow-x-auto rounded-xl border border-border bg-card"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-2.5 font-medium">Mother</th>
                                <th class="px-4 py-2.5 font-medium">G / P</th>
                                <th class="px-4 py-2.5 font-medium">
                                    Gestation
                                </th>
                                <th class="px-4 py-2.5 font-medium">EDD</th>
                                <th class="px-4 py-2.5 font-medium">ANC</th>
                                <th class="px-4 py-2.5 font-medium">Risk</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="p in pregnancies" :key="p.id">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-pink-500/10 text-[11px] font-semibold text-pink-700"
                                            >{{ p.patient.initials }}</span
                                        >
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">
                                                {{ p.patient.name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                <span class="font-mono">{{
                                                    p.patient.file_number
                                                }}</span
                                                >{{
                                                    p.patient.age !== null
                                                        ? ' · ' +
                                                          p.patient.age +
                                                          'y'
                                                        : ''
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    {{ p.gravida ?? '—' }} / {{ p.para ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    {{
                                        p.ga_weeks !== null
                                            ? p.ga_weeks + ' wks'
                                            : '—'
                                    }}
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p>{{ p.edd ?? '—' }}</p>
                                    <p
                                        class="text-xs"
                                        :class="
                                            p.overdue
                                                ? 'font-medium text-red-600'
                                                : p.due_soon
                                                  ? 'text-amber-600'
                                                  : 'text-muted-foreground'
                                        "
                                    >
                                        {{ p.edd_diff }}
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p>{{ p.anc_visits }} visits</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            p.last_anc
                                                ? 'last ' + p.last_anc
                                                : 'none yet'
                                        }}
                                    </p>
                                </td>
                                <td class="max-w-56 px-4 py-2.5">
                                    <span
                                        v-if="p.risk_factors.length"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"
                                        :title="p.risk_factors.join(', ')"
                                    >
                                        <AlertTriangle class="size-3" />
                                        {{ p.risk_factors.length }} risk
                                        {{
                                            p.risk_factors.length === 1
                                                ? 'factor'
                                                : 'factors'
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >Low risk</span
                                    >
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="p.url">Open</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <Baby class="size-4 text-pink-600" />
                    Recent deliveries
                </h2>
                <div
                    v-if="!recentDeliveries.length"
                    class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                >
                    No deliveries recorded yet.
                </div>
                <ul
                    v-else
                    class="divide-y divide-border rounded-xl border border-border bg-card"
                >
                    <li v-for="d in recentDeliveries" :key="d.id" class="p-3">
                        <div class="flex items-start justify-between gap-2">
                            <Link
                                :href="d.url"
                                class="font-medium hover:underline"
                                >{{ d.mother }}</Link
                            >
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                                >{{ d.delivered_at }}</span
                            >
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ d.mode }} · {{ d.live }} live of {{ d.babies }}
                            <span v-if="d.maternal_outcome !== 'Well'">
                                · {{ d.maternal_outcome }}</span
                            >
                        </p>
                    </li>
                </ul>
            </aside>
        </div>

        <!-- Book pregnancy dialog -->
        <Dialog v-model:open="bookOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Book pregnancy</DialogTitle>
                    <DialogDescription>
                        Open the antenatal record. Enter the LMP and the EDD is
                        worked out; enter the EDD alone when the LMP is unknown.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label>Mother *</Label>
                        <div
                            v-if="selected"
                            class="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <span>
                                <span class="font-medium">{{
                                    selected.name
                                }}</span>
                                <span class="text-muted-foreground">
                                    ·
                                    <span class="font-mono">{{
                                        selected.file_number
                                    }}</span
                                    >{{
                                        selected.age !== null
                                            ? ' · ' + selected.age + 'y'
                                            : ''
                                    }}</span
                                >
                            </span>
                            <button
                                type="button"
                                class="text-xs text-muted-foreground hover:underline"
                                @click="
                                    selected = null;
                                    form.patient_id = '';
                                "
                            >
                                Change
                            </button>
                        </div>
                        <div v-else class="relative">
                            <Search
                                class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                            />
                            <Input
                                v-model="patientQuery"
                                class="pl-8"
                                placeholder="Search female patients by name or file number"
                                autocomplete="off"
                            />
                            <ul
                                v-if="results.length"
                                class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-popover text-sm shadow-md"
                            >
                                <li
                                    v-for="p in results"
                                    :key="p.id"
                                    class="flex items-center justify-between px-3 py-2"
                                    :class="
                                        p.has_active_pregnancy
                                            ? 'opacity-60'
                                            : 'cursor-pointer hover:bg-muted'
                                    "
                                    @click="
                                        !p.has_active_pregnancy && choose(p)
                                    "
                                >
                                    <span>
                                        <span class="font-medium">{{
                                            p.name
                                        }}</span>
                                        <span class="text-muted-foreground">
                                            · {{ p.file_number
                                            }}{{
                                                p.age !== null
                                                    ? ' · ' + p.age + 'y'
                                                    : ''
                                            }}</span
                                        >
                                    </span>
                                    <span
                                        v-if="p.has_active_pregnancy"
                                        class="text-xs text-muted-foreground"
                                        >already booked</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <InputError :message="form.errors.patient_id" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="lmp">Last menstrual period</Label>
                            <Input id="lmp" v-model="form.lmp" type="date" />
                            <InputError :message="form.errors.lmp" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="edd"
                                >Expected delivery (if known)</Label
                            >
                            <Input id="edd" v-model="form.edd" type="date" />
                            <InputError :message="form.errors.edd" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="gravida">Gravida</Label>
                            <Input
                                id="gravida"
                                v-model="form.gravida"
                                type="number"
                                min="1"
                                max="20"
                            />
                            <InputError :message="form.errors.gravida" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="para">Para</Label>
                            <Input
                                id="para"
                                v-model="form.para"
                                type="number"
                                min="0"
                                max="20"
                            />
                            <InputError :message="form.errors.para" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="booking_date">Booking date</Label>
                            <Input
                                id="booking_date"
                                v-model="form.booking_date"
                                type="date"
                            />
                            <InputError :message="form.errors.booking_date" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Risk factors</Label>
                        <div class="grid gap-1.5 sm:grid-cols-2">
                            <Label
                                v-for="risk in riskFactors"
                                :key="risk"
                                class="flex items-center gap-2 text-sm font-normal"
                            >
                                <Checkbox
                                    :model-value="
                                        form.risk_factors.includes(risk)
                                    "
                                    @update:model-value="
                                        (v) => toggleRisk(risk, v === true)
                                    "
                                />
                                {{ risk }}
                            </Label>
                        </div>
                        <InputError :message="form.errors.risk_factors" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="notes">Notes</Label>
                        <Input
                            id="notes"
                            v-model="form.notes"
                            placeholder="Optional"
                        />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="bookOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="form.processing || !selected"
                        >
                            <Spinner v-if="form.processing" />
                            <Baby v-else class="size-4" />
                            Book
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
