<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Package, Plus, Search, TriangleAlert } from '@lucide/vue';
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

type Item = {
    id: number;
    code: string;
    name: string;
    label: string;
    category: string;
    unit: string;
    selling_price: number;
    quantity_on_hand: number;
    reorder_level: number;
    is_low: boolean;
    next_expiry: string | null;
    url: string;
};

const props = defineProps<{
    items: Item[];
    filters: { q: string };
    counts: { total: number; low_stock: number };
    categories: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }] },
});

const search = ref(props.filters.q ?? '');
const addOpen = ref(false);

const form = useForm({
    code: '',
    name: '',
    category: 'drug',
    form: '',
    strength: '',
    unit: 'each',
    cost_price: '',
    selling_price: '',
    reorder_level: '0',
});

function runSearch() {
    router.get(
        '/inventory',
        { q: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function addItem() {
    form.post('/inventory', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            addOpen.value = false;
        },
    });
}

function money(v: number): string {
    return `₦${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="Inventory" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Inventory / Store
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ counts.total }} items ·
                    <span
                        :class="
                            counts.low_stock
                                ? 'font-medium text-amber-700 dark:text-amber-400'
                                : ''
                        "
                        >{{ counts.low_stock }} low on stock</span
                    >
                </p>
            </div>
            <Button type="button" @click="addOpen = true">
                <Plus class="size-4" />
                Add item
            </Button>
        </div>

        <div class="relative sm:max-w-xs">
            <Search
                class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                placeholder="Search name or code…"
                class="pl-8"
                @keyup.enter="runSearch"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Item</th>
                        <th class="px-4 py-2.5 font-medium">Stock</th>
                        <th class="px-4 py-2.5 font-medium">Reorder</th>
                        <th class="px-4 py-2.5 font-medium">Price</th>
                        <th class="px-4 py-2.5 font-medium">Next expiry</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="cursor-pointer hover:bg-muted/40"
                        @click="router.visit(item.url)"
                    >
                        <td class="px-4 py-2.5">
                            <div class="font-medium text-foreground">
                                {{ item.label }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                <span class="font-mono">{{ item.code }}</span>
                                · {{ item.category }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="inline-flex items-center gap-1 font-medium"
                                :class="
                                    item.is_low
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : 'text-foreground'
                                "
                            >
                                <TriangleAlert
                                    v-if="item.is_low"
                                    class="size-3.5"
                                />
                                {{ item.quantity_on_hand }} {{ item.unit }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ item.reorder_level }}
                        </td>
                        <td class="px-4 py-2.5 text-foreground">
                            {{ money(item.selling_price) }}
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ item.next_expiry ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td
                            colspan="5"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            <Package
                                class="mx-auto mb-2 size-6 text-muted-foreground/60"
                            />
                            No items found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add item dialog -->
        <Dialog v-model:open="addOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add inventory item</DialogTitle>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="addItem"
                >
                    <div class="grid gap-1.5">
                        <Label>Code *</Label>
                        <Input
                            v-model="form.code"
                            placeholder="e.g. DRG-1001"
                        />
                        <InputError :message="form.errors.code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Name *</Label>
                        <Input v-model="form.name" placeholder="e.g. Aspirin" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Category *</Label>
                        <Select v-model="form.category">
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
                        <Label>Unit *</Label>
                        <Input
                            v-model="form.unit"
                            placeholder="tablet, vial…"
                        />
                        <InputError :message="form.errors.unit" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Form</Label>
                        <Input v-model="form.form" placeholder="Tablet" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Strength</Label>
                        <Input v-model="form.strength" placeholder="500mg" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Cost price</Label>
                        <Input
                            v-model="form.cost_price"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Selling price *</Label>
                        <Input
                            v-model="form.selling_price"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <InputError :message="form.errors.selling_price" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Reorder level *</Label>
                        <Input
                            v-model="form.reorder_level"
                            type="number"
                            placeholder="0"
                        />
                        <InputError :message="form.errors.reorder_level" />
                    </div>
                    <div class="flex items-end sm:col-span-2">
                        <Button type="submit" :disabled="form.processing">
                            <Plus class="size-4" />
                            Add item
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
