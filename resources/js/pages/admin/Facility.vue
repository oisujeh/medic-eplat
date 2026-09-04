<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import FacilityProfileFields from '@/components/facility/FacilityProfileFields.vue';
import type { FacilityProfileForm } from '@/components/facility/FacilityProfileFields.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/admin/facility';
import type { FacilityProfile } from '@/types';

const props = defineProps<{
    profile: FacilityProfile;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin/users' },
            { title: 'Facility profile', href: '/admin/facility' },
        ],
    },
});

const form = useForm<FacilityProfileForm & { notice: string }>({
    name: props.profile.name ?? '',
    code: props.profile.code ?? '',
    state: props.profile.state ?? '',
    lga: props.profile.lga ?? '',
    notice: props.profile.notice ?? '',
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
