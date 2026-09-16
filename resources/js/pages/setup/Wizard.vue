<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Hash,
    Layers,
    MapPin,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FacilityProfileFields from '@/components/facility/FacilityProfileFields.vue';
import type { FacilityProfileForm } from '@/components/facility/FacilityProfileFields.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { logout } from '@/routes';
import { store } from '@/routes/setup';
import type { FacilityProfile, SharedData } from '@/types';

type Option = { value: string; label: string };

const props = defineProps<{
    profile: FacilityProfile;
    options: { levels: Option[]; ownerships: Option[]; tierProfiles: Option[] };
}>();

const page = usePage<SharedData>();
const adminName = computed(() => page.props.auth.user?.name ?? '');

type WizardForm = FacilityProfileForm & {
    level: string;
    ownership: string;
    tier_profile: string;
    patient_number_prefix: string;
    patient_number_check_digit: boolean;
    billing_enabled: boolean;
};

const form = useForm<WizardForm>({
    name: props.profile.name ?? '',
    code: props.profile.code ?? '',
    state: props.profile.state ?? '',
    lga: props.profile.lga ?? '',
    level: props.profile.level ?? '',
    ownership: props.profile.ownership ?? '',
    tier_profile: props.profile.tier_profile ?? '',
    patient_number_prefix: props.profile.patient_number_prefix ?? 'MEP',
    patient_number_check_digit: props.profile.patient_number_check_digit,
    billing_enabled: props.profile.billing_enabled,
});

type StepKey = 'identity' | 'location' | 'profile' | 'numbering' | 'review';
type TextField = Exclude<
    keyof WizardForm,
    'patient_number_check_digit' | 'billing_enabled'
>;

const steps: Array<{ key: StepKey; title: string; fields: TextField[] }> = [
    { key: 'identity', title: 'Facility', fields: ['name', 'code'] },
    { key: 'location', title: 'Location', fields: ['state', 'lga'] },
    {
        key: 'profile',
        title: 'Profile',
        fields: ['level', 'ownership', 'tier_profile'],
    },
    { key: 'numbering', title: 'Numbering', fields: ['patient_number_prefix'] },
    { key: 'review', title: 'Review', fields: [] },
];

const current = ref(0);
const step = computed(() => steps[current.value]);
const isLast = computed(() => current.value === steps.length - 1);

// The shared fields component shows one group at a time on the first two steps.
const fieldSection = computed(() =>
    step.value.key === 'identity' || step.value.key === 'location'
        ? step.value.key
        : undefined,
);

// Each step's fields must be filled before moving on; the server validates
// everything again on submit.
const canContinue = computed(() =>
    step.value.fields.every((field) => form[field].trim() !== ''),
);

function next() {
    if (!canContinue.value || isLast.value) {
        return;
    }

    step.value.fields.forEach((field) => form.clearErrors(field));
    current.value += 1;
}

function back() {
    if (current.value > 0) {
        current.value -= 1;
    }
}

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onError: (errors) => {
            // Jump to the first step that still has something to fix.
            const failing = steps.findIndex((s) =>
                s.fields.some((field) => field in errors),
            );

            if (failing >= 0) {
                current.value = failing;
            }
        },
    });
}

function labelFor(options: Option[], value: string): string {
    return options.find((o) => o.value === value)?.label ?? value;
}

// The same Luhn digit the server appends, so the preview is exact.
function luhn(digits: string): number {
    let sum = 0;
    let double = true;

    for (let i = digits.length - 1; i >= 0; i--) {
        let digit = Number(digits[i]);

        if (double) {
            digit *= 2;
            digit = digit > 9 ? digit - 9 : digit;
        }

        sum += digit;
        double = !double;
    }

    return (10 - (sum % 10)) % 10;
}

const sampleNumber = computed(() => {
    const prefix = (form.patient_number_prefix || 'MEP').toUpperCase();
    const year = new Date().getFullYear();
    const check = form.patient_number_check_digit
        ? `-${luhn(`${year}000001`)}`
        : '';

    return `${prefix}/${year}/000001${check}`;
});

const summary = computed(() => [
    { label: 'Facility name', value: form.name },
    { label: 'Facility code', value: form.code, mono: true },
    { label: 'State', value: form.state },
    { label: 'LGA', value: form.lga },
    {
        label: 'Level of care',
        value: labelFor(props.options.levels, form.level),
    },
    {
        label: 'Ownership',
        value: labelFor(props.options.ownerships, form.ownership),
    },
    {
        label: 'Profile',
        value: labelFor(props.options.tierProfiles, form.tier_profile),
    },
    { label: 'File numbers', value: sampleNumber.value, mono: true },
    { label: 'Billing', value: form.billing_enabled ? 'On' : 'Off' },
]);
</script>

<template>
    <Head title="Set up your facility" />

    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <header
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
                >
                    <AppLogoIcon
                        class="size-5 fill-current text-white dark:text-black"
                    />
                </div>
                <div class="grid text-sm leading-tight">
                    <span class="font-semibold">Medic-EPlat HIS</span>
                    <span class="text-xs text-muted-foreground"
                        >First-run setup</span
                    >
                </div>
            </div>

            <div class="flex items-center gap-3 text-sm text-muted-foreground">
                <span v-if="adminName" class="hidden sm:inline"
                    >Signed in as
                    <span class="font-medium text-foreground">{{
                        adminName
                    }}</span></span
                >
                <Link
                    :href="logout()"
                    as="button"
                    class="text-sm underline-offset-4 hover:underline"
                    >Sign out</Link
                >
            </div>
        </header>

        <main class="flex flex-1 items-start justify-center px-4 py-10">
            <div class="w-full max-w-2xl">
                <div class="mb-8">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Set up your facility
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tell Medic-EPlat which facility this installation
                        serves. These details appear on every screen and report,
                        and can be changed later under Administration.
                    </p>
                </div>

                <ol
                    class="mb-6 flex items-center gap-2 text-sm"
                    aria-label="Setup steps"
                >
                    <template v-for="(s, i) in steps" :key="s.key">
                        <li
                            class="flex items-center gap-2"
                            :aria-current="i === current ? 'step' : undefined"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full border text-xs font-semibold"
                                :class="
                                    i < current
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : i === current
                                          ? 'border-primary text-primary'
                                          : 'border-border text-muted-foreground'
                                "
                            >
                                <Check v-if="i < current" class="size-3.5" />
                                <template v-else>{{ i + 1 }}</template>
                            </span>
                            <span
                                class="hidden sm:inline"
                                :class="
                                    i === current
                                        ? 'font-medium'
                                        : 'text-muted-foreground'
                                "
                                >{{ s.title }}</span
                            >
                        </li>
                        <li
                            v-if="i < steps.length - 1"
                            class="h-px flex-1 bg-border"
                            aria-hidden="true"
                        ></li>
                    </template>
                </ol>

                <form @submit.prevent="isLast ? submit() : next()">
                    <Card>
                        <CardHeader>
                            <template v-if="step.key === 'identity'">
                                <CardTitle class="flex items-center gap-2">
                                    <Building2
                                        class="size-4 text-muted-foreground"
                                    />
                                    About the facility
                                </CardTitle>
                                <CardDescription>
                                    The name staff and patients know the
                                    facility by, and the code it is registered
                                    under.
                                </CardDescription>
                            </template>
                            <template v-else-if="step.key === 'location'">
                                <CardTitle class="flex items-center gap-2">
                                    <MapPin
                                        class="size-4 text-muted-foreground"
                                    />
                                    Where the facility is
                                </CardTitle>
                                <CardDescription>
                                    The state and local government area the
                                    facility is located in.
                                </CardDescription>
                            </template>
                            <template v-else-if="step.key === 'profile'">
                                <CardTitle class="flex items-center gap-2">
                                    <Layers
                                        class="size-4 text-muted-foreground"
                                    />
                                    What kind of facility
                                </CardTitle>
                                <CardDescription>
                                    The level of care, who owns it, and the
                                    configuration profile that decides which
                                    modules, forms and reports are on.
                                </CardDescription>
                            </template>
                            <template v-else-if="step.key === 'numbering'">
                                <CardTitle class="flex items-center gap-2">
                                    <Hash
                                        class="size-4 text-muted-foreground"
                                    />
                                    File numbers and billing
                                </CardTitle>
                                <CardDescription>
                                    How hospital numbers print on cards and
                                    folders, and whether the facility charges
                                    for care.
                                </CardDescription>
                            </template>
                            <template v-else>
                                <CardTitle class="flex items-center gap-2">
                                    <Check
                                        class="size-4 text-muted-foreground"
                                    />
                                    Review and finish
                                </CardTitle>
                                <CardDescription>
                                    Check the details below, then finish setup
                                    to open the system.
                                </CardDescription>
                            </template>
                        </CardHeader>

                        <CardContent>
                            <FacilityProfileFields
                                v-if="fieldSection"
                                v-model:name="form.name"
                                v-model:code="form.code"
                                v-model:state="form.state"
                                v-model:lga="form.lga"
                                :errors="form.errors"
                                :section="fieldSection"
                            />

                            <div
                                v-else-if="step.key === 'profile'"
                                class="grid gap-5"
                            >
                                <div class="grid gap-1.5">
                                    <Label for="setup-level"
                                        >Level of care *</Label
                                    >
                                    <Select v-model="form.level">
                                        <SelectTrigger
                                            id="setup-level"
                                            class="w-full"
                                        >
                                            <SelectValue
                                                placeholder="Select level"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="o in options.levels"
                                                :key="o.value"
                                                :value="o.value"
                                                >{{ o.label }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.level" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="setup-ownership"
                                        >Ownership *</Label
                                    >
                                    <Select v-model="form.ownership">
                                        <SelectTrigger
                                            id="setup-ownership"
                                            class="w-full"
                                        >
                                            <SelectValue
                                                placeholder="Select ownership"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="o in options.ownerships"
                                                :key="o.value"
                                                :value="o.value"
                                                >{{ o.label }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.ownership"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="setup-tier"
                                        >Configuration profile *</Label
                                    >
                                    <Select v-model="form.tier_profile">
                                        <SelectTrigger
                                            id="setup-tier"
                                            class="w-full"
                                        >
                                            <SelectValue
                                                placeholder="Select profile"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="o in options.tierProfiles"
                                                :key="o.value"
                                                :value="o.value"
                                                >{{ o.label }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-muted-foreground">
                                        PHC, secondary, tertiary or private. The
                                        same software runs everywhere; the
                                        profile switches modules on and off.
                                    </p>
                                    <InputError
                                        :message="form.errors.tier_profile"
                                    />
                                </div>
                            </div>

                            <div
                                v-else-if="step.key === 'numbering'"
                                class="grid gap-5"
                            >
                                <div class="grid gap-1.5">
                                    <Label for="setup-prefix"
                                        >File number prefix *</Label
                                    >
                                    <Input
                                        id="setup-prefix"
                                        v-model="form.patient_number_prefix"
                                        class="font-mono uppercase"
                                        maxlength="6"
                                        autocomplete="off"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Numbers will print as
                                        <span class="font-mono">{{
                                            sampleNumber
                                        }}</span
                                        >. Up to six letters or digits.
                                    </p>
                                    <InputError
                                        :message="
                                            form.errors.patient_number_prefix
                                        "
                                    />
                                </div>
                                <label class="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        :model-value="
                                            form.patient_number_check_digit
                                        "
                                        @update:model-value="
                                            (v) =>
                                                (form.patient_number_check_digit =
                                                    v === true)
                                        "
                                    />
                                    <span>
                                        Add a check digit
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            Catches a mistyped or misread file
                                            number at the desk.
                                        </span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        :model-value="form.billing_enabled"
                                        @update:model-value="
                                            (v) =>
                                                (form.billing_enabled =
                                                    v === true)
                                        "
                                    />
                                    <span>
                                        This facility bills for care
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            Off for facilities where care is
                                            free: no charges post and no prices
                                            show. Can be changed later.
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <dl
                                v-else
                                class="divide-y divide-border rounded-lg border border-border"
                            >
                                <div
                                    v-for="row in summary"
                                    :key="row.label"
                                    class="grid grid-cols-[10rem_1fr] gap-3 px-4 py-2.5 text-sm"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ row.label }}
                                    </dt>
                                    <dd
                                        class="font-medium"
                                        :class="{ 'font-mono': row.mono }"
                                    >
                                        {{ row.value || '—' }}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <div class="mt-5 flex items-center justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="current === 0 || form.processing"
                            @click="back"
                        >
                            <ChevronLeft class="size-4" />
                            Back
                        </Button>

                        <Button
                            v-if="!isLast"
                            type="submit"
                            :disabled="!canContinue"
                        >
                            Continue
                            <ChevronRight class="size-4" />
                        </Button>
                        <Button
                            v-else
                            type="submit"
                            :disabled="form.processing"
                            data-test="finish-setup"
                        >
                            <Spinner v-if="form.processing" />
                            <Check v-else class="size-4" />
                            Finish setup
                        </Button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>
