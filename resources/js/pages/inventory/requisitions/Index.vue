<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowLeftRight, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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

type Requisition = {
    id: number;
    number: string;
    status: { value: string; label: string; tone: string };
    from: string | null;
    to: string | null;
    lines_count: number;
    requested_by: string | null;
    requested_at: string | null;
    url: string;
};

type Item = {
    id: number;
    label: string;
    unit: string;
    quantity_on_hand: number;
};

const props = defineProps<{
    requisitions: Requisition[];
    filters: { status: string };
    statuses: Array<{ value: string; label: string }>;
    locations: Array<{ id: number; name: string; kind: string }>;
    default_from_id: number;
    default_to_id: number;
    items: Item[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inventory', href: '/inventory' },
            { title: 'Requisitions', href: '/inventory/requisitions' },
        ],
    },
});

const status = ref(props.filters.status);
const newOpen = ref(false);
const itemSearch = ref('');

type Line = { item: Item; quantity: number };
const lines = ref<Line[]>([]);

const form = useForm({
    from_location_id: String(props.default_from_id),
    to_location_id: String(props.default_to_id),
    note: '',
});

const filteredItems = computed(() => {
    const q = itemSearch.value.trim().toLowerCase();

    return props.items
        .filter((i) => !lines.value.some((l) => l.item.id === i.id))
        .filter((i) => !q || i.label.toLowerCase().includes(q))
        .slice(0, 12);
});

const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const linesError = computed(() => {
    const key = Object.keys(formErrors.value).find((k) =>
        k.startsWith('lines'),
    );

    return key ? formErrors.value[key] : undefined;
});

function filterStatus(value: string) {
    status.value = value;
    router.get(
        '/inventory/requisitions',
        { status: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function addLine(item: Item) {
    lines.value.push({ item, quantity: 1 });
    itemSearch.value = '';
}

function removeLine(i: number) {
    lines.value.splice(i, 1);
}

function submit() {
    form.transform((data) => ({
        ...data,
        from_location_id: Number(data.from_location_id),
        to_location_id: Number(data.to_location_id),
        note: data.note || null,
        lines: lines.value.map((l) => ({
            inventory_item_id: l.item.id,
            quantity: l.quantity,
        })),
    })).post('/inventory/requisitions', {
        onSuccess: () => {
            lines.value = [];
            form.reset();
            newOpen.value = false;
        },
    });
}

function toneClass(tone: string): string {
    return (
        {
            amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
            violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
            green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        }[tone] ?? 'bg-muted text-muted-foreground'
    );
}
</script>

<template>
    <Head title="Requisitions" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <Link
            href="/inventory"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to store
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Stock requisitions
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Stock moving between the store and the places that dispense
                    it.
                </p>
            </div>
            <Button type="button" @click="newOpen = true">
                <Plus class="size-4" />
                New requisition
            </Button>
        </div>

        <div
            class="flex w-fit flex-wrap items-center gap-1 rounded-lg border border-border bg-card p-1"
        >
            <button
                type="button"
                class="rounded-md px-3 py-1 text-sm"
                :class="
                    status === 'open'
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="filterStatus('open')"
            >
                Open
            </button>
            <button
                v-for="s in statuses"
                :key="s.value"
                type="button"
                class="rounded-md px-3 py-1 text-sm"
                :class="
                    status === s.value
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="filterStatus(s.value)"
            >
                {{ s.label }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Number</th>
                        <th class="px-4 py-2.5 font-medium">From → To</th>
                        <th class="px-4 py-2.5 font-medium">Lines</th>
                        <th class="px-4 py-2.5 font-medium">Raised</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="r in requisitions"
                        :key="r.id"
                        class="cursor-pointer hover:bg-muted/40"
                        @click="router.visit(r.url)"
                    >
                        <td class="px-4 py-2.5 font-mono text-xs font-medium">
                            {{ r.number }}
                        </td>
                        <td class="px-4 py-2.5">
                            {{ r.from }}
                            <span class="text-muted-foreground">→</span>
                            {{ r.to }}
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ r.lines_count }}
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ r.requested_at }}
                            <span v-if="r.requested_by"
                                >· {{ r.requested_by }}</span
                            >
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="toneClass(r.status.tone)"
                                >{{ r.status.label }}</span
                            >
                        </td>
                    </tr>
                    <tr v-if="!requisitions.length">
                        <td
                            colspan="5"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            <ArrowLeftRight
                                class="mx-auto mb-2 size-6 text-muted-foreground/60"
                            />
                            No requisitions here.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- New requisition dialog -->
        <Dialog v-model:open="newOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>New requisition</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Request from *</Label>
                            <Select v-model="form.from_location_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="l in locations"
                                        :key="l.id"
                                        :value="String(l.id)"
                                        >{{ l.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.from_location_id"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Deliver to *</Label>
                            <Select v-model="form.to_location_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="l in locations"
                                        :key="l.id"
                                        :value="String(l.id)"
                                        >{{ l.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.to_location_id" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Add items</Label>
                        <Input
                            v-model="itemSearch"
                            placeholder="Search the catalogue…"
                        />
                        <div
                            v-if="itemSearch.trim()"
                            class="max-h-40 divide-y divide-border/60 overflow-y-auto rounded-md border border-border"
                        >
                            <button
                                v-for="i in filteredItems"
                                :key="i.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted/40"
                                @click="addLine(i)"
                            >
                                <span>{{ i.label }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >{{ i.quantity_on_hand }} {{ i.unit }} in
                                    facility</span
                                >
                            </button>
                            <p
                                v-if="!filteredItems.length"
                                class="px-3 py-4 text-center text-xs text-muted-foreground"
                            >
                                No items match.
                            </p>
                        </div>
                    </div>

                    <ul
                        v-if="lines.length"
                        class="flex flex-col divide-y divide-border rounded-md border border-border"
                    >
                        <li
                            v-for="(l, i) in lines"
                            :key="l.item.id"
                            class="flex items-center gap-2 px-3 py-2"
                        >
                            <span class="min-w-0 flex-1 truncate text-sm">{{
                                l.item.label
                            }}</span>
                            <Input
                                v-model.number="l.quantity"
                                type="number"
                                min="1"
                                class="h-8 w-20"
                            />
                            <span class="text-xs text-muted-foreground">{{
                                l.item.unit
                            }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                                @click="removeLine(i)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No items added yet.
                    </p>
                    <InputError :message="linesError" />

                    <div class="grid gap-1.5">
                        <Label>Note</Label>
                        <Input
                            v-model="form.note"
                            placeholder="e.g. Weekly top-up"
                        />
                    </div>

                    <div>
                        <Button
                            type="submit"
                            :disabled="!lines.length || form.processing"
                        >
                            <ArrowLeftRight class="size-4" />
                            Raise requisition
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
