<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import ClaimsNav from '@/components/claims/ClaimsNav.vue';
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
import { naira } from '@/lib/money';

type Payer = {
    id: number;
    name: string;
    code: string;
    type: string;
    type_label: string;
    discount_percent: number;
    drug_copay_percent: number;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    patients_count: number;
    draft_claims_count: number;
    outstanding_amount: number;
};

defineProps<{
    payers: Payer[];
    payerTypes: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Claims', href: '/claims' },
            { title: 'Payers', href: '/claims/payers' },
        ],
    },
});

const blank = {
    name: '',
    code: '',
    type: 'hmo',
    discount_percent: 0,
    drug_copay_percent: 0,
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    is_active: true,
};

const addOpen = ref(false);
const addForm = useForm({ ...blank });

function submitAdd() {
    addForm.post('/claims/payers', {
        preserveScroll: true,
        onSuccess: () => {
            addOpen.value = false;
            addForm.reset();
        },
    });
}

const editing = ref<Payer | null>(null);
const editForm = useForm({ ...blank });

function openEdit(payer: Payer) {
    editing.value = payer;
    editForm.clearErrors();
    editForm.name = payer.name;
    editForm.code = payer.code;
    editForm.type = payer.type;
    editForm.discount_percent = payer.discount_percent;
    editForm.drug_copay_percent = payer.drug_copay_percent;
    editForm.contact_person = payer.contact_person ?? '';
    editForm.phone = payer.phone ?? '';
    editForm.email = payer.email ?? '';
    editForm.address = payer.address ?? '';
    editForm.notes = payer.notes ?? '';
    editForm.is_active = payer.is_active;
}

function submitEdit() {
    if (!editing.value) {
        return;
    }

    editForm.patch(`/claims/payers/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}
</script>

<template>
    <Head title="Payers" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Payers</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    The NHIA, HMOs and corporate schemes the facility claims
                    from, with the tariff rules applied to their enrollees'
                    bills.
                </p>
            </div>
            <Button @click="addOpen = true">
                <Plus class="size-4" />
                Add payer
            </Button>
        </div>

        <ClaimsNav current="payers" />

        <div
            v-if="!payers.length"
            class="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground"
        >
            No payers yet. Add the NHIA and the HMOs you hold contracts with.
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
                        <th class="px-4 py-2.5 font-medium">Payer</th>
                        <th class="px-4 py-2.5 font-medium">Type</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Discount
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Drug co-pay
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Enrollees
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Outstanding
                        </th>
                        <th class="px-4 py-2.5 font-medium">Contact</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="p in payers"
                        :key="p.id"
                        :class="{ 'opacity-60': !p.is_active }"
                    >
                        <td class="px-4 py-2.5">
                            <p class="font-medium">{{ p.name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ p.code }}
                                <span v-if="!p.is_active"> · inactive</span>
                            </p>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            {{ p.type_label }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ p.discount_percent }}%
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ p.drug_copay_percent }}%
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ p.patients_count }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ naira(p.outstanding_amount, 0) }}
                        </td>
                        <td class="px-4 py-2.5 text-xs text-muted-foreground">
                            <p v-if="p.contact_person">
                                {{ p.contact_person }}
                            </p>
                            <p v-if="p.phone">{{ p.phone }}</p>
                            <p v-if="p.email">{{ p.email }}</p>
                        </td>
                        <td class="px-2 py-2.5 text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="Edit payer"
                                @click="openEdit(p)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add dialog -->
        <Dialog v-model:open="addOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add payer</DialogTitle>
                    <DialogDescription>
                        Tariff rules apply to every claim raised for the payer's
                        enrollees; individual lines can still be adjusted on a
                        draft claim.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submitAdd"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="add-name">Name *</Label>
                        <Input id="add-name" v-model="addForm.name" />
                        <InputError :message="addForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-code">Code *</Label>
                        <Input
                            id="add-code"
                            v-model="addForm.code"
                            class="font-mono uppercase"
                            maxlength="20"
                        />
                        <InputError :message="addForm.errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Type *</Label>
                        <Select v-model="addForm.type">
                            <SelectTrigger class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in payerTypes"
                                    :key="t.value"
                                    :value="t.value"
                                    >{{ t.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="addForm.errors.type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-discount">Tariff discount (%)</Label>
                        <Input
                            id="add-discount"
                            v-model.number="addForm.discount_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                        />
                        <InputError
                            :message="addForm.errors.discount_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-copay">Drug co-payment (%)</Label>
                        <Input
                            id="add-copay"
                            v-model.number="addForm.drug_copay_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                        />
                        <InputError
                            :message="addForm.errors.drug_copay_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-contact">Contact person</Label>
                        <Input
                            id="add-contact"
                            v-model="addForm.contact_person"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-phone">Phone</Label>
                        <Input id="add-phone" v-model="addForm.phone" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="add-email">Claims email</Label>
                        <Input
                            id="add-email"
                            v-model="addForm.email"
                            type="email"
                        />
                        <InputError :message="addForm.errors.email" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="add-address">Address</Label>
                        <Input id="add-address" v-model="addForm.address" />
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="addOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="addForm.processing">
                            <Spinner v-if="addForm.processing" />
                            Add payer
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog
            :open="editing !== null"
            @update:open="
                (v: boolean) => {
                    if (!v) editing = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {{ editing?.name }}</DialogTitle>
                    <DialogDescription>
                        Changes apply to claims raised from now on.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submitEdit"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="edit-name">Name *</Label>
                        <Input id="edit-name" v-model="editForm.name" />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-code">Code *</Label>
                        <Input
                            id="edit-code"
                            v-model="editForm.code"
                            class="font-mono uppercase"
                            maxlength="20"
                        />
                        <InputError :message="editForm.errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Type *</Label>
                        <Select v-model="editForm.type">
                            <SelectTrigger class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in payerTypes"
                                    :key="t.value"
                                    :value="t.value"
                                    >{{ t.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-discount">Tariff discount (%)</Label>
                        <Input
                            id="edit-discount"
                            v-model.number="editForm.discount_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                        />
                        <InputError
                            :message="editForm.errors.discount_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-copay">Drug co-payment (%)</Label>
                        <Input
                            id="edit-copay"
                            v-model.number="editForm.drug_copay_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.5"
                        />
                        <InputError
                            :message="editForm.errors.drug_copay_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-contact">Contact person</Label>
                        <Input
                            id="edit-contact"
                            v-model="editForm.contact_person"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-phone">Phone</Label>
                        <Input id="edit-phone" v-model="editForm.phone" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="edit-email">Claims email</Label>
                        <Input
                            id="edit-email"
                            v-model="editForm.email"
                            type="email"
                        />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="edit-address">Address</Label>
                        <Input id="edit-address" v-model="editForm.address" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="edit-notes">Notes</Label>
                        <Input id="edit-notes" v-model="editForm.notes" />
                    </div>
                    <Label
                        class="flex items-center gap-2 text-sm font-normal sm:col-span-2"
                    >
                        <Checkbox v-model="editForm.is_active" />
                        Accepting claims
                    </Label>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editing = null"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="editForm.processing">
                            <Spinner v-if="editForm.processing" />
                            Save payer
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
