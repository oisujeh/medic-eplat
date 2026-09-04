<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Pill, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Medication } from '@/types/clinical';

/**
 * Prescribe, edit, stop and remove the patient's medications.
 */
const props = defineProps<{
    medications: Medication[];
    /** encounters.medications.store */
    action: string;
    disabled?: boolean;
}>();

const form = useForm({ name: '', dose: '', frequency: '', route: '' });
const editingId = ref<number | null>(null);

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => cancelEdit() };

    if (editingId.value !== null) {
        form.patch(`${props.action}/${editingId.value}`, opts);
    } else {
        form.post(props.action, opts);
    }
}

function edit(m: Medication) {
    editingId.value = m.id;
    form.name = m.name;
    form.dose = m.dose ?? '';
    form.frequency = m.frequency ?? '';
    form.route = m.route ?? '';
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function stop(id: number) {
    router.post(`${props.action}/${id}/stop`, {}, { preserveScroll: true });
}

function remove(id: number) {
    router.delete(`${props.action}/${id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-5">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold">
            <Pill class="size-4 text-primary" />
            {{ editingId ? 'Edit medication' : 'Medications' }}
        </h2>
        <form
            v-if="!disabled"
            class="grid gap-3 sm:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5 sm:col-span-3">
                <Label>Medication *</Label>
                <Input v-model="form.name" placeholder="e.g. Amlodipine" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label>Dose</Label>
                <Input v-model="form.dose" placeholder="e.g. 5mg" />
            </div>
            <div class="grid gap-1.5">
                <Label>Frequency</Label>
                <Input v-model="form.frequency" placeholder="e.g. OD" />
            </div>
            <div class="grid gap-1.5">
                <Label>Route</Label>
                <Input v-model="form.route" placeholder="e.g. PO" />
            </div>
            <div class="flex gap-2 sm:col-span-3">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="size-4" />
                    {{ editingId ? 'Save changes' : 'Prescribe drug' }}
                </Button>
                <Button
                    v-if="editingId"
                    type="button"
                    variant="ghost"
                    @click="cancelEdit"
                >
                    Cancel
                </Button>
            </div>
        </form>
        <ul
            v-if="medications.length"
            class="flex flex-col divide-y divide-border"
            :class="disabled ? '' : 'mt-4 border-t border-border'"
        >
            <li
                v-for="m in medications"
                :key="m.id"
                class="flex items-center justify-between gap-2 py-2.5"
            >
                <span class="text-sm text-foreground">{{ m.label }}</span>
                <div v-if="!disabled" class="flex items-center gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground hover:text-foreground"
                        @click="stop(m.id)"
                    >
                        Stop
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-muted-foreground hover:text-foreground"
                        aria-label="Edit medication"
                        @click="edit(m)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                        aria-label="Remove medication"
                        @click="remove(m.id)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>
        <p v-else-if="disabled" class="text-sm text-muted-foreground">
            No active medications.
        </p>
    </div>
</template>
