<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Syringe, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Immunization } from '@/types/clinical';

/**
 * Vaccines given during a nursing encounter.
 */
const props = defineProps<{
    immunizations: Immunization[];
    /** encounters.immunizations.store */
    action: string;
    disabled?: boolean;
}>();

const form = useForm({
    vaccine: '',
    dose_label: '',
    batch_no: '',
    site: '',
    route: 'IM',
    notes: '',
});

function add() {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(id: number) {
    router.delete(`${props.action}/${id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-5">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
            <Syringe class="size-4 text-primary" />
            Immunizations
        </h2>

        <ul
            v-if="immunizations.length"
            class="mb-3 flex flex-col divide-y divide-border"
        >
            <li
                v-for="imm in immunizations"
                :key="imm.id"
                class="flex items-center justify-between gap-2 py-2 first:pt-0"
            >
                <div>
                    <p class="text-sm font-medium">{{ imm.label }}</p>
                    <p class="text-xs text-muted-foreground">
                        <span v-if="imm.route">{{ imm.route }}</span>
                        <span v-if="imm.site"> · {{ imm.site }}</span>
                        <span v-if="imm.batch_no">
                            · Batch {{ imm.batch_no }}</span
                        >
                        <span v-if="imm.administered_at">
                            · {{ imm.administered_at }}</span
                        >
                    </p>
                </div>
                <Button
                    v-if="!disabled"
                    variant="ghost"
                    size="icon"
                    aria-label="Remove immunization"
                    @click="remove(imm.id)"
                >
                    <Trash2 class="size-4 text-muted-foreground" />
                </Button>
            </li>
        </ul>
        <p v-else-if="disabled" class="text-sm text-muted-foreground">
            No vaccines recorded.
        </p>

        <form
            v-if="!disabled"
            class="grid gap-2 sm:grid-cols-2 md:grid-cols-3"
            @submit.prevent="add"
        >
            <Input v-model="form.vaccine" placeholder="Vaccine (e.g. Penta)" />
            <Input v-model="form.dose_label" placeholder="Dose (e.g. OPV 1)" />
            <Input v-model="form.batch_no" placeholder="Batch no." />
            <Input v-model="form.site" placeholder="Site" />
            <Input v-model="form.route" placeholder="Route (e.g. IM)" />
            <Button type="submit" :disabled="!form.vaccine || form.processing">
                <Plus class="size-4" /> Add
            </Button>
        </form>
        <InputError :message="form.errors.vaccine" />
    </div>
</template>
