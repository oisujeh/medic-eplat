<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Package,
    Plus,
    Search,
    Settings2,
    TriangleAlert,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

type Item = {
    id: number;
    code: string;
    name: string;
    label: string;
    category: string;
    unit: string;
    selling_price: number;
    quantity_on_hand: number;
    at_location: number | null;
    reorder_level: number;
    is_low: boolean;
    next_expiry: string | null;
    url: string;
};

const props = defineProps<{
    items: Item[];
    filters: { q: string; location: string | null };
    counts: { total: number; low_stock: number };
    categories: Array<{ value: string; label: string }>;
    locations: Array<{ id: number; slug: string; name: string; kind: string }>;
    reorder: {
        lead_time_days: number;
        min_cover_months: number;
        max_cover_months: number;
        consumption_window_days: number;
    };
    can: { manage_settings: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inventory', href: '/inventory' }] },
});

const search = ref(props.filters.q ?? '');
const location = ref(props.filters.location ?? 'all');
const addOpen = ref(false);
const settingsOpen = ref(false);

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

const settingsForm = useForm({
    lead_time_days: String(props.reorder.lead_time_days),
    min_cover_months: String(props.reorder.min_cover_months),
    max_cover_months: String(props.reorder.max_cover_months),
    consumption_window_days: String(props.reorder.consumption_window_days),
});

function runSearch() {
    router.get(
        '/inventory',
        {
            q: search.value,
            location: location.value === 'all' ? undefined : location.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function pickLocation(slug: string) {
    location.value = slug;
    runSearch();
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

function saveSettings() {
    settingsForm.patch('/inventory/settings', {
        preserveScroll: true,
        onSuccess: () => {
            settingsOpen.value = false;
        },
    });
}

function applySuggestions() {
    router.post(
        '/inventory/reorder-levels/apply',
        {},
        { preserveScroll: true },
    );
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
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link href="/inventory/requisitions">
                        <ArrowLeftRight class="size-4" />
                        Requisitions
                    </Link>
                </Button>
                <Button
                    v-if="can.manage_settings"
                    type="button"
                    variant="outline"
                    @click="settingsOpen = true"
                >
                    <Settings2 class="size-4" />
                    Reorder planning
                </Button>
                <Button type="button" @click="addOpen = true">
                    <Plus class="size-4" />
                    Add item
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative sm:w-72">
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
            <div
                v-if="locations.length > 1"
                class="flex flex-wrap items-center gap-1 rounded-lg border border-border bg-card p-1"
            >
                <button
                    type="button"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="
                        location === 'all'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="pickLocation('all')"
                >
                    All locations
                </button>
                <button
                    v-for="l in locations"
                    :key="l.id"
                    type="button"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="
                        location === l.slug
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="pickLocation(l.slug)"
                >
                    {{ l.name }}
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Item</th>
                        <th
                            v-if="location !== 'all'"
                            class="px-4 py-2.5 font-medium"
                        >
                            Here
                        </th>
                        <th class="px-4 py-2.5 font-medium">
                            {{ location !== 'all' ? 'Facility' : 'Stock' }}
                        </th>
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
                        <td
                            v-if="location !== 'all'"
                            class="px-4 py-2.5 font-medium"
                        >
                            {{ item.at_location ?? 0 }} {{ item.unit }}
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
                            :colspan="location !== 'all' ? 6 : 5"
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

        <!-- Reorder planning dialog -->
        <Dialog v-model:open="settingsOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reorder planning</DialogTitle>
                    <DialogDescription>
                        Reorder suggestions come from what was actually issued
                        over the consumption window. The suggested reorder level
                        covers the lead time plus the minimum cover; the
                        suggested order brings stock up to the maximum cover.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="saveSettings"
                >
                    <div class="grid gap-1.5">
                        <Label>Lead time (days)</Label>
                        <Input
                            v-model="settingsForm.lead_time_days"
                            type="number"
                            min="0"
                        />
                        <InputError
                            :message="settingsForm.errors.lead_time_days"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Consumption window (days)</Label>
                        <Input
                            v-model="settingsForm.consumption_window_days"
                            type="number"
                            min="7"
                        />
                        <InputError
                            :message="
                                settingsForm.errors.consumption_window_days
                            "
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Minimum cover (months)</Label>
                        <Input
                            v-model="settingsForm.min_cover_months"
                            type="number"
                            step="0.5"
                            min="0"
                        />
                        <InputError
                            :message="settingsForm.errors.min_cover_months"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Maximum cover (months)</Label>
                        <Input
                            v-model="settingsForm.max_cover_months"
                            type="number"
                            step="0.5"
                            min="0"
                        />
                        <InputError
                            :message="settingsForm.errors.max_cover_months"
                        />
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-2 sm:col-span-2"
                    >
                        <Button
                            type="submit"
                            :disabled="settingsForm.processing"
                        >
                            Save settings
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="applySuggestions"
                        >
                            Apply suggested reorder levels
                        </Button>
                        <Button as-child variant="ghost">
                            <Link href="/reports/reorder-suggestions"
                                >View suggestions</Link
                            >
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
