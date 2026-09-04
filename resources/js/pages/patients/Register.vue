<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ClipboardList, Info } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
import statesData from '@/data/state_lga.json';

type StateEntry = { state: string; alias: string; lgas: string[] };

const props = defineProps<{
    options: {
        titles: string[];
        sexes: Record<string, string>;
        maritalStatuses: string[];
        nokRelationships: string[];
        coverages: Record<string, string>;
        hmoProviders: string[];
        payers: Array<{ id: number; name: string; type_label: string }>;
        visitCategories: string[];
        outpatientServices: string[];
    };
    recentPatients: Array<{
        file_number: string;
        name: string;
        sex: string;
        age: number | null;
        visit_category: string;
        registered_at: string | null;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Patient Registration', href: '/registration' }],
    },
});

const states = (statesData as StateEntry[]).map((entry) => entry.state).sort();

const form = useForm({
    title: '',
    surname: '',
    first_name: '',
    other_names: '',
    date_of_birth: '',
    sex: '',
    marital_status: '',
    phone: '',
    email: '',
    address: '',
    nationality: 'Nigerian',
    state: '',
    lga: '',
    next_of_kin_name: '',
    next_of_kin_relationship: '',
    next_of_kin_phone: '',
    coverage: 'private',
    payer_id: '',
    hmo_name: '',
    hmo_number: '',
    hmo_plan: '',
    hmo_expires_at: '',
    is_transfer: false,
    transfer_from: '',
    transfer_reason: '',
    transfer_service: '',
    visit_category: 'Outpatient',
    outpatient_service: 'Clinical Consultation & Diagnosis',
    outpatient_service_other: '',
});

// Only the LGAs of the selected state are offered — the core of the dynamic
// State → LGA behaviour.
const lgasForState = computed<string[]>(
    () =>
        (statesData as StateEntry[]).find((entry) => entry.state === form.state)
            ?.lgas ?? [],
);

// Clearing a stale LGA whenever the state changes keeps the two fields in sync.
watch(
    () => form.state,
    () => {
        if (!lgasForState.value.includes(form.lga)) {
            form.lga = '';
        }
    },
);

const isHmo = computed(() => form.coverage === 'hmo');

// Payers registered under Claims are preferred; the free-text provider list
// remains for a facility that has not set any up yet.
const hasPayers = computed(() => props.options.payers.length > 0);

watch(
    () => form.payer_id,
    (id) => {
        const payer = props.options.payers.find((p) => String(p.id) === id);

        if (payer) {
            form.hmo_name = payer.name;
        }
    },
);
const isOutpatient = computed(() => form.visit_category === 'Outpatient');
const isOtherService = computed(
    () => isOutpatient.value && form.outpatient_service === 'Other',
);

function submit() {
    form.transform((data) => ({
        ...data,
        payer_id:
            data.coverage === 'hmo' && data.payer_id
                ? Number(data.payer_id)
                : null,
        hmo_name: data.coverage === 'hmo' ? data.hmo_name : '',
        hmo_number: data.coverage === 'hmo' ? data.hmo_number : '',
        hmo_plan: data.coverage === 'hmo' ? data.hmo_plan : '',
        hmo_expires_at:
            data.coverage === 'hmo' && data.hmo_expires_at
                ? data.hmo_expires_at
                : null,
        transfer_from: data.is_transfer ? data.transfer_from : '',
        transfer_reason: data.is_transfer ? data.transfer_reason : '',
        transfer_service: data.is_transfer ? data.transfer_service : '',
        outpatient_service: !isOutpatient.value
            ? ''
            : data.outpatient_service === 'Other'
              ? data.outpatient_service_other
              : data.outpatient_service,
    })).post('/registration', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Patient Registration" />

    <div class="flex h-full flex-1 flex-col gap-1 p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Register new patient
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Records Unit — demographics &amp; visit routing only. Do not
                    enter clinical notes or assessment here; that is completed
                    by the receiving service after routing.
                </p>
            </div>
            <span
                class="hidden shrink-0 items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground sm:inline-flex"
            >
                <ClipboardList class="size-3.5" />
                File number auto-generated on save
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <form
                class="flex flex-1 flex-col gap-6 p-4"
                @submit.prevent="submit"
            >
                <!-- Patient identity -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Patient identity
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="title">Title</Label>
                            <Select v-model="form.title">
                                <SelectTrigger id="title" class="w-full">
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="t in options.titles"
                                        :key="t"
                                        :value="t"
                                        >{{ t }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="surname">Surname *</Label>
                            <Input
                                id="surname"
                                v-model="form.surname"
                                autocomplete="off"
                                placeholder="e.g. Chukwu"
                            />
                            <InputError :message="form.errors.surname" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="first_name">First name *</Label>
                            <Input
                                id="first_name"
                                v-model="form.first_name"
                                autocomplete="off"
                                placeholder="e.g. Ngozi"
                            />
                            <InputError :message="form.errors.first_name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="other_names">Other names</Label>
                            <Input
                                id="other_names"
                                v-model="form.other_names"
                                autocomplete="off"
                                placeholder="Middle name(s)"
                            />
                            <InputError :message="form.errors.other_names" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="date_of_birth">Date of birth</Label>
                            <Input
                                id="date_of_birth"
                                type="date"
                                v-model="form.date_of_birth"
                            />
                            <InputError :message="form.errors.date_of_birth" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="sex">Sex *</Label>
                            <Select v-model="form.sex">
                                <SelectTrigger id="sex" class="w-full">
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, value) in options.sexes"
                                        :key="value"
                                        :value="value"
                                        >{{ label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.sex" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="marital_status">Marital status</Label>
                            <Select v-model="form.marital_status">
                                <SelectTrigger
                                    id="marital_status"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in options.maritalStatuses"
                                        :key="m"
                                        :value="m"
                                        >{{ m }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.marital_status" />
                        </div>
                    </div>
                </section>

                <!-- Contact & residence -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Contact &amp; residence
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="phone">Phone number</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                autocomplete="off"
                                placeholder="e.g. 0803 123 4567"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                v-model="form.email"
                                autocomplete="off"
                                placeholder="optional"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="nationality">Nationality *</Label>
                            <Input
                                id="nationality"
                                v-model="form.nationality"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.nationality" />
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2 lg:col-span-3">
                            <Label for="address">Residential address</Label>
                            <Input
                                id="address"
                                v-model="form.address"
                                autocomplete="off"
                                placeholder="House number, street, area"
                            />
                            <InputError :message="form.errors.address" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="state">State of residence *</Label>
                            <Select v-model="form.state">
                                <SelectTrigger id="state" class="w-full">
                                    <SelectValue placeholder="Select state" />
                                </SelectTrigger>
                                <SelectContent class="max-h-72">
                                    <SelectItem
                                        v-for="s in states"
                                        :key="s"
                                        :value="s"
                                        >{{ s }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.state" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="lga">LGA of residence *</Label>
                            <Select v-model="form.lga" :disabled="!form.state">
                                <SelectTrigger id="lga" class="w-full">
                                    <SelectValue
                                        :placeholder="
                                            form.state
                                                ? 'Select LGA'
                                                : 'Select a state first'
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent class="max-h-72">
                                    <SelectItem
                                        v-for="l in lgasForState"
                                        :key="l"
                                        :value="l"
                                        >{{ l }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.lga" />
                        </div>
                    </div>
                </section>

                <!-- Next of kin -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Next of kin
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="nok_name">Name</Label>
                            <Input
                                id="nok_name"
                                v-model="form.next_of_kin_name"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors.next_of_kin_name"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="nok_rel">Relationship</Label>
                            <Select v-model="form.next_of_kin_relationship">
                                <SelectTrigger id="nok_rel" class="w-full">
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="r in options.nokRelationships"
                                        :key="r"
                                        :value="r"
                                        >{{ r }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.next_of_kin_relationship"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="nok_phone">Phone</Label>
                            <Input
                                id="nok_phone"
                                v-model="form.next_of_kin_phone"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors.next_of_kin_phone"
                            />
                        </div>
                    </div>
                </section>

                <!-- Billing coverage -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Billing coverage
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="coverage">Coverage *</Label>
                            <Select v-model="form.coverage">
                                <SelectTrigger id="coverage" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(
                                            label, value
                                        ) in options.coverages"
                                        :key="value"
                                        :value="value"
                                        >{{ label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.coverage" />
                        </div>

                        <div v-if="isHmo && hasPayers" class="grid gap-1.5">
                            <Label for="payer_id">HMO / scheme *</Label>
                            <Select v-model="form.payer_id">
                                <SelectTrigger id="payer_id" class="w-full">
                                    <SelectValue placeholder="Select payer" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="p in options.payers"
                                        :key="p.id"
                                        :value="String(p.id)"
                                        >{{ p.name }}
                                        <span class="text-muted-foreground"
                                            >· {{ p.type_label }}</span
                                        ></SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="
                                    form.errors.payer_id ?? form.errors.hmo_name
                                "
                            />
                        </div>

                        <div v-else-if="isHmo" class="grid gap-1.5">
                            <Label for="hmo_name">HMO / provider *</Label>
                            <Select v-model="form.hmo_name">
                                <SelectTrigger id="hmo_name" class="w-full">
                                    <SelectValue
                                        placeholder="Select provider"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="h in options.hmoProviders"
                                        :key="h"
                                        :value="h"
                                        >{{ h }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.hmo_name" />
                        </div>

                        <div v-if="isHmo" class="grid gap-1.5">
                            <Label for="hmo_number">Enrollee number</Label>
                            <Input
                                id="hmo_number"
                                v-model="form.hmo_number"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.hmo_number" />
                        </div>

                        <div v-if="isHmo" class="grid gap-1.5">
                            <Label for="hmo_plan">Plan / scheme</Label>
                            <Input
                                id="hmo_plan"
                                v-model="form.hmo_plan"
                                autocomplete="off"
                                placeholder="e.g. Formal sector, Gold"
                            />
                            <InputError :message="form.errors.hmo_plan" />
                        </div>

                        <div v-if="isHmo" class="grid gap-1.5">
                            <Label for="hmo_expires_at"
                                >Enrolment expires</Label
                            >
                            <Input
                                id="hmo_expires_at"
                                v-model="form.hmo_expires_at"
                                type="date"
                            />
                            <InputError :message="form.errors.hmo_expires_at" />
                        </div>
                    </div>
                </section>

                <!-- Inter-facility transfer -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <Label
                        class="flex cursor-pointer items-center gap-2.5 text-sm font-medium"
                    >
                        <Checkbox v-model="form.is_transfer" />
                        This patient is being transferred from another facility
                    </Label>

                    <div
                        v-if="form.is_transfer"
                        class="mt-4 grid gap-4 sm:grid-cols-2"
                    >
                        <div class="grid gap-1.5">
                            <Label for="transfer_from"
                                >Transferred from (facility) *</Label
                            >
                            <Input
                                id="transfer_from"
                                v-model="form.transfer_from"
                                autocomplete="off"
                                placeholder="e.g. St. Mary's Clinic, Yaba"
                            />
                            <InputError :message="form.errors.transfer_from" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="transfer_reason"
                                >Reason for transfer</Label
                            >
                            <Input
                                id="transfer_reason"
                                v-model="form.transfer_reason"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors.transfer_reason"
                            />
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label for="transfer_service"
                                >Service we should provide</Label
                            >
                            <Input
                                id="transfer_service"
                                v-model="form.transfer_service"
                                autocomplete="off"
                                placeholder="Brief routing note only"
                            />
                            <InputError
                                :message="form.errors.transfer_service"
                            />
                        </div>
                    </div>
                </section>

                <!-- Visit category & routing -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">
                        Visit category &amp; routing
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="visit_category">Visit category *</Label>
                            <Select v-model="form.visit_category">
                                <SelectTrigger
                                    id="visit_category"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="v in options.visitCategories"
                                        :key="v"
                                        :value="v"
                                        >{{ v }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.visit_category" />
                        </div>

                        <div v-if="isOutpatient" class="grid gap-1.5">
                            <Label for="outpatient_service"
                                >Outpatient service point *</Label
                            >
                            <Select v-model="form.outpatient_service">
                                <SelectTrigger
                                    id="outpatient_service"
                                    class="w-full"
                                >
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="s in options.outpatientServices"
                                        :key="s"
                                        :value="s"
                                        >{{ s }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.outpatient_service"
                            />
                        </div>

                        <div
                            v-if="isOtherService"
                            class="grid gap-1.5 sm:col-span-2"
                        >
                            <Label for="outpatient_service_other"
                                >Specify other service *</Label
                            >
                            <Input
                                id="outpatient_service_other"
                                v-model="form.outpatient_service_other"
                                autocomplete="off"
                                placeholder="e.g. Physiotherapy, Dental walk-in"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="form.reset()"
                    >
                        Clear
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Register &amp; route patient
                    </Button>
                </div>
            </form>

            <!-- Recently registered -->
            <!--            <aside class="flex flex-col gap-4">
                <div class="rounded-xl border border-border bg-card p-5">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground"
                    >
                        <ClipboardList class="size-4 text-muted-foreground" />
                        Recently registered
                    </h2>
                    <ul
                        v-if="props.recentPatients.length"
                        class="flex flex-col divide-y divide-border"
                    >
                        <li
                            v-for="p in props.recentPatients"
                            :key="p.file_number"
                            class="flex flex-col gap-0.5 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span class="text-sm font-medium">{{ p.name }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ p.file_number }} ·
                                {{ p.age !== null ? p.age + '' + p.sex : p.sex }}
                                · {{ p.visit_category }}
                            </span>
                            <span
                                v-if="p.registered_at"
                                class="text-xs text-muted-foreground/70"
                                >{{ p.registered_at }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No patients registered yet.
                    </p>
                </div>

                <div
                    class="flex gap-2.5 rounded-xl border border-border bg-muted/40 p-4 text-xs text-muted-foreground"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <p>
                        Only demographic and routing details are captured here.
                        Vitals, complaints and assessment are recorded by the
                        receiving service.
                    </p>
                </div>
            </aside>-->
        </div>
    </div>
</template>
