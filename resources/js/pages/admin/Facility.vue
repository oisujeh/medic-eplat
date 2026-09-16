<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import FacilityProfileFields from '@/components/facility/FacilityProfileFields.vue';
import type { FacilityProfileForm } from '@/components/facility/FacilityProfileFields.vue';
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
import { update } from '@/routes/admin/facility';
import type { FacilityProfile } from '@/types';

type Option = { value: string; label: string };

const props = defineProps<{
    profile: FacilityProfile;
    options: {
        levels: Option[];
        ownerships: Option[];
        tierProfiles: Option[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin/users' },
            { title: 'Facility profile', href: '/admin/facility' },
        ],
    },
});

const form = useForm<
    FacilityProfileForm & {
        level: string;
        ownership: string;
        tier_profile: string;
        dhis2_org_unit: string;
        notice: string;
        billing_enabled: boolean;
        patient_number_prefix: string;
        patient_number_check_digit: boolean;
    }
>({
    name: props.profile.name ?? '',
    code: props.profile.code ?? '',
    state: props.profile.state ?? '',
    lga: props.profile.lga ?? '',
    level: props.profile.level ?? '',
    ownership: props.profile.ownership ?? '',
    tier_profile: props.profile.tier_profile ?? '',
    dhis2_org_unit: props.profile.dhis2_org_unit ?? '',
    notice: props.profile.notice ?? '',
    billing_enabled: props.profile.billing_enabled,
    patient_number_prefix: props.profile.patient_number_prefix ?? '',
    patient_number_check_digit: props.profile.patient_number_check_digit,
});

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50';

function submit() {
    form.patch(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Facility profile" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Facility profile
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                The facility details captured during first-run setup. They
                appear across the system and on printed documents.
            </p>
        </div>

        <AdminNav current="facility" />

        <form
            class="max-w-xl rounded-xl border border-border bg-card p-5"
            @submit.prevent="submit"
        >
            <FacilityProfileFields
                v-model:name="form.name"
                v-model:code="form.code"
                v-model:state="form.state"
                v-model:lga="form.lga"
                :errors="form.errors"
            />

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="facility-level">Level of care</Label>
                    <Select v-model="form.level">
                        <SelectTrigger id="facility-level" class="w-full">
                            <SelectValue placeholder="Select level" />
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
                    <Label for="facility-ownership">Ownership</Label>
                    <Select v-model="form.ownership">
                        <SelectTrigger id="facility-ownership" class="w-full">
                            <SelectValue placeholder="Select ownership" />
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
                    <InputError :message="form.errors.ownership" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="facility-tier">Configuration profile</Label>
                    <Select v-model="form.tier_profile">
                        <SelectTrigger id="facility-tier" class="w-full">
                            <SelectValue placeholder="Select profile" />
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
                        Which bundle of modules, forms and reports the facility
                        runs.
                    </p>
                    <InputError :message="form.errors.tier_profile" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="facility-dhis2">DHIS2 organisation unit</Label>
                    <Input
                        id="facility-dhis2"
                        v-model="form.dhis2_org_unit"
                        class="font-mono"
                        maxlength="11"
                        autocomplete="off"
                        placeholder="e.g. a1B2c3D4e5F"
                    />
                    <p class="text-xs text-muted-foreground">
                        The 11-character UID the NHMIS return is filed under.
                    </p>
                    <InputError :message="form.errors.dhis2_org_unit" />
                </div>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="facility-prefix">File number prefix</Label>
                    <Input
                        id="facility-prefix"
                        v-model="form.patient_number_prefix"
                        class="font-mono uppercase"
                        maxlength="6"
                        autocomplete="off"
                        placeholder="MEP"
                    />
                    <p class="text-xs text-muted-foreground">
                        Hospital numbers print as PREFIX/year/sequence, e.g.
                        {{
                            (form.patient_number_prefix || 'MEP').toUpperCase()
                        }}/2026/000123.
                    </p>
                    <InputError :message="form.errors.patient_number_prefix" />
                </div>

                <div class="grid gap-3 pt-1">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox
                            :model-value="form.patient_number_check_digit"
                            @update:model-value="
                                (v) =>
                                    (form.patient_number_check_digit =
                                        v === true)
                            "
                        />
                        <span>
                            Add a check digit to file numbers
                            <span class="block text-xs text-muted-foreground">
                                Catches a mistyped or misread number at the
                                desk.
                            </span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox
                            :model-value="form.billing_enabled"
                            @update:model-value="
                                (v) => (form.billing_enabled = v === true)
                            "
                        />
                        <span>
                            Billing is switched on
                            <span class="block text-xs text-muted-foreground">
                                When off, no charges post and no prices are
                                shown anywhere in the workflow.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.billing_enabled" />
                </div>
            </div>

            <div class="mt-5 grid gap-1.5">
                <Label for="facility-notice">Home-screen notice</Label>
                <textarea
                    id="facility-notice"
                    v-model="form.notice"
                    :class="textareaClass"
                    rows="3"
                    maxlength="500"
                    placeholder="e.g. Grand round moves to Friday this week. Leave blank to clear the board."
                />
                <p class="text-xs text-muted-foreground">
                    Shown to every member of staff at the top of their dashboard
                    until you clear it.
                </p>
                <InputError :message="form.errors.notice" />
            </div>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    <Save v-else class="size-4" />
                    Save changes
                </Button>
                <p
                    v-if="form.recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </div>
        </form>
    </div>
</template>
