<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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

type Service = {
    id: number;
    code: string;
    name: string;
    label: string;
    category: string;
    category_label: string;
    unit: string | null;
    price: number;
    is_active: boolean;
};

defineProps<{
    services: Service[];
    categories: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Billing', href: '/billing' },
            { title: 'Fee schedule', href: '/billing/services' },
        ],
    },
});

const addOpen = ref(false);
const addForm = useForm({
    code: '',
    name: '',
    category: 'other',
    unit: '',
    price: '',
});

function add() {
    addForm.post('/billing/services', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            addOpen.value = false;
        },
    });
}

const editing = ref<Service | null>(null);
const editForm = useForm({
    name: '',
    category: 'other',
    unit: '',
    price: '',
    is_active: true,
});

function openEdit(s: Service) {
    editing.value = s;
    editForm.name = s.name;
    editForm.category = s.category;
    editForm.unit = s.unit ?? '';
    editForm.price = String(s.price);
    editForm.is_active = s.is_active;
    editForm.clearErrors();
}

function saveEdit() {
    if (!editing.value) {
        return;
    }

    editForm.patch(`/billing/services/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="Fee schedule" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <Link
            href="/billing"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to billing
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Fee schedule
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Prices for consultations, admission, bed/room and
                    procedures.
                </p>
            </div>
            <Button type="button" @click="addOpen = true">
                <Plus class="size-4" />
                Add service
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Service</th>
                        <th class="px-4 py-2.5 font-medium">Category</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Price
                        </th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="s in services" :key="s.id">
                        <td class="px-4 py-2.5">
                            <div
                                class="font-medium"
                                :class="
                                    s.is_active
                                        ? 'text-foreground'
                                        : 'text-muted-foreground line-through'
                                "
                            >
                                {{ s.name
                                }}<span
                                    v-if="s.unit"
                                    class="font-normal text-muted-foreground"
                                >
                                    · {{ s.unit }}</span
                                >
                            </div>
                            <div
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ s.code }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                                >{{ s.category_label }}</span
                            >
                        </td>
                        <td class="px-4 py-2.5 text-right font-medium">
                            {{ money(s.price) }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                v-if="s.is_active"
                                class="text-xs font-medium text-green-700 dark:text-green-400"
                                >Active</span
                            >
                            <span v-else class="text-xs text-muted-foreground"
                                >Inactive</span
                            >
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:text-foreground"
                                @click="openEdit(s)"
                            >
                                <Pencil class="size-4" />
                                Edit
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!services.length">
                        <td
                            colspan="5"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            No services yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add dialog -->
        <Dialog v-model:open="addOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add service</DialogTitle>
                </DialogHeader>
                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="add">
                    <div class="grid gap-1.5">
                        <Label>Code *</Label>
                        <Input v-model="addForm.code" placeholder="e.g. XRAY" />
                        <InputError :message="addForm.errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Name *</Label>
                        <Input
                            v-model="addForm.name"
                            placeholder="e.g. Physiotherapy"
                        />
                        <InputError :message="addForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Category *</Label>
                        <Select v-model="addForm.category">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in categories"
                                    :key="c.value"
                                    :value="c.value"
                                    >{{ c.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Unit</Label>
                        <Input
                            v-model="addForm.unit"
                            placeholder="per day, each…"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Price *</Label>
                        <Input
                            v-model="addForm.price"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <InputError :message="addForm.errors.price" />
                    </div>
                    <div class="flex items-end">
                        <Button type="submit" :disabled="addForm.processing">
                            <Plus class="size-4" />
                            Add
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
                    <DialogTitle>Edit {{ editing?.code }}</DialogTitle>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="saveEdit"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Name *</Label>
                        <Input v-model="editForm.name" />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Category *</Label>
                        <Select v-model="editForm.category">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in categories"
                                    :key="c.value"
                                    :value="c.value"
                                    >{{ c.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Unit</Label>
                        <Input v-model="editForm.unit" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Price *</Label>
                        <Input
                            v-model="editForm.price"
                            type="number"
                            step="0.01"
                        />
                        <InputError :message="editForm.errors.price" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Status</Label>
                        <Select
                            :model-value="editForm.is_active ? '1' : '0'"
                            @update:model-value="
                                (v) => (editForm.is_active = v === '1')
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end sm:col-span-2">
                        <Button type="submit" :disabled="editForm.processing">
                            Save changes
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
