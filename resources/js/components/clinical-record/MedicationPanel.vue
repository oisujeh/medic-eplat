<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Pill, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, toRef, watch } from 'vue';
import ConceptPicker from '@/components/clinical-record/ConceptPicker.vue';
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
import { useProductSearch } from '@/composables/useProductSearch';
import type { Medication } from '@/types/clinical';

/**
 * Prescribe, edit, stop and remove the patient's medications. A prescription
 * is coded to a drug concept and, when the drug is stocked, to the product
 * the pharmacy should supply.
 */
const props = defineProps<{
    medications: Medication[];
    /** encounters.medications.store */
    action: string;
    disabled?: boolean;
}>();

const form = useForm({
    name: '',
    concept_id: null as number | null,
    inventory_item_id: null as number | null,
    dose: '',
    frequency: '',
    route: '',
    quantity: '' as string | number,
    duration_days: '' as string | number,
});
const editingId = ref<number | null>(null);

const { products } = useProductSearch(
    toRef(form, 'concept_id'),
    toRef(form, 'name'),
);

// A product that no longer matches the drug is dropped.
watch(products, (list) => {
    if (
        form.inventory_item_id !== null &&
        !list.some((p) => p.id === form.inventory_item_id)
    ) {
        form.inventory_item_id = null;
    }
});

const productValue = computed({
    get: () =>
        form.inventory_item_id === null
            ? 'none'
            : String(form.inventory_item_id),
    set: (v: string) => {
        form.inventory_item_id = v === 'none' ? null : Number(v);
    },
});

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => cancelEdit() };

    form.transform((data) => ({
        ...data,
        quantity: data.quantity === '' ? null : Number(data.quantity),
        duration_days:
            data.duration_days === '' ? null : Number(data.duration_days),
    }));

    if (editingId.value !== null) {
        form.patch(`${props.action}/${editingId.value}`, opts);
    } else {
        form.post(props.action, opts);
    }
}

function edit(m: Medication) {
    editingId.value = m.id;
    form.name = m.name;
    form.concept_id = m.concept_id;
    form.inventory_item_id = m.inventory_item_id;
    form.dose = m.dose ?? '';
    form.frequency = m.frequency ?? '';
    form.route = m.route ?? '';
    form.quantity = m.quantity ?? '';
    form.duration_days = m.duration_days ?? '';
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

function toneClass(tone: string): string {
    return (
        {
            amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
            green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        }[tone] ?? 'bg-muted text-muted-foreground'
    );
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
            <div class="grid gap-1.5 sm:col-span-2">
                <Label>Medication *</Label>
                <ConceptPicker
                    v-model="form.name"
                    v-model:concept-id="form.concept_id"
                    concept-class="drug"
                    placeholder="e.g. Amlodipine"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label>Stocked product</Label>
                <Select v-model="productValue">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">
                            {{
                                products.length
                                    ? 'Any / not specified'
                                    : 'Not stocked'
                            }}
                        </SelectItem>
                        <SelectItem
                            v-for="p in products"
                            :key="p.id"
                            :value="String(p.id)"
                        >
                            {{ p.label }}
                            <span class="text-muted-foreground">
                                · {{ p.quantity_on_hand }} {{ p.unit }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.inventory_item_id" />
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
            <div class="grid gap-1.5">
                <Label>Quantity to supply</Label>
                <Input
                    v-model="form.quantity"
                    type="number"
                    min="1"
                    placeholder="e.g. 30"
                />
                <InputError :message="form.errors.quantity" />
            </div>
            <div class="grid gap-1.5">
                <Label>Duration (days)</Label>
                <Input
                    v-model="form.duration_days"
                    type="number"
                    min="1"
                    placeholder="e.g. 30"
                />
                <InputError :message="form.errors.duration_days" />
            </div>
            <div class="flex items-end gap-2">
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
                <div class="min-w-0">
                    <span class="text-sm text-foreground">{{ m.label }}</span>
                    <span
                        v-if="m.quantity"
                        class="text-xs text-muted-foreground"
                    >
                        · {{ m.quantity }} to supply</span
                    >
                    <span
                        v-if="m.duration_days"
                        class="text-xs text-muted-foreground"
                    >
                        · {{ m.duration_days }} days</span
                    >
                    <span
                        class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="toneClass(m.fulfilment.tone)"
                        >{{ m.fulfilment.label }}</span
                    >
                </div>
                <div
                    v-if="!disabled"
                    class="flex shrink-0 items-center gap-0.5"
                >
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
