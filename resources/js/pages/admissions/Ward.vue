<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BedDouble, Pencil, Plus, Wrench } from '@lucide/vue';
import { ref } from 'vue';
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

type Occupant = {
    name: string;
    initials: string;
    file_number: string;
    sex: string;
    age: number | null;
    attending: string | null;
    admitted_diff: string | null;
    days: number | null;
    url: string;
};

type BedCard = {
    id: number;
    label: string;
    status: string;
    status_label: string;
    tone: string;
    notes: string | null;
    occupant: Occupant | null;
};

const props = defineProps<{
    ward: {
        id: number;
        name: string;
        code: string;
        type: string;
        type_label: string;
        description: string | null;
        is_active: boolean;
        bed_service_charge_id: number | null;
        bed_charge: { name: string; price: number } | null;
        total: number;
        available: number;
        occupied: number;
        out_of_service: number;
    };
    beds: BedCard[];
    wardTypes: Array<{ value: string; label: string }>;
    bedCharges: Array<{ id: number; name: string; price: number }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admissions', href: '/admissions' },
            { title: 'Ward', href: '#' },
        ],
    },
});

function bedClass(tone: string): string {
    const map: Record<string, string> = {
        green: 'border-emerald-500/40 bg-emerald-500/5',
        blue: 'border-primary/40 bg-primary/5',
        muted: 'border-border bg-muted/40 opacity-70',
    };

    return map[tone] ?? map.muted;
}

function badgeClass(tone: string): string {
    const map: Record<string, string> = {
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        blue: 'bg-primary/10 text-primary',
        muted: 'bg-muted text-muted-foreground',
    };

    return map[tone] ?? map.muted;
}

function money(v: number): string {
    return '₦' + v.toLocaleString('en-NG', { minimumFractionDigits: 0 });
}

// --- Bed status toggles ---
const busyBed = ref<number | null>(null);

function setBedStatus(bed: BedCard, status: 'available' | 'out_of_service') {
    busyBed.value = bed.id;
    router.patch(
        `/admissions/beds/${bed.id}`,
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                busyBed.value = null;
            },
        },
    );
}

// --- Add beds dialog ---
const addOpen = ref(false);
const addForm = useForm({ count: 4, prefix: 'Bed' });

function submitAdd() {
    addForm.post(`/admissions/wards/${props.ward.id}/beds`, {
        preserveScroll: true,
        onSuccess: () => {
            addOpen.value = false;
            addForm.reset();
        },
    });
}

// --- Edit ward dialog ---
const editOpen = ref(false);
const editForm = useForm({
    name: props.ward.name,
    code: props.ward.code,
    type: props.ward.type,
    bed_service_charge_id: props.ward.bed_service_charge_id
        ? String(props.ward.bed_service_charge_id)
        : '',
    description: props.ward.description ?? '',
    is_active: props.ward.is_active,
});

function submitEdit() {
    editForm
        .transform((data) => ({
            ...data,
            bed_service_charge_id: data.bed_service_charge_id
                ? Number(data.bed_service_charge_id)
                : null,
        }))
        .patch(`/admissions/wards/${props.ward.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        });
}
</script>

<template>
    <Head :title="ward.name" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link href="/admissions" aria-label="Back to admissions">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                    >
                        <BedDouble class="size-5 text-muted-foreground" />
                        {{ ward.name }}
                        <span
                            v-if="!ward.is_active"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >Inactive</span
                        >
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="font-mono">{{ ward.code }}</span>
                        · {{ ward.type_label }}
                        <span v-if="ward.bed_charge">
                            · {{ ward.bed_charge.name }},
                            {{ money(ward.bed_charge.price) }} per day</span
                        >
                        <span v-else> · no bed charge</span>
                    </p>
                    <p
                        v-if="ward.description"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ ward.description }}
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="editOpen = true">
                    <Pencil class="size-4" />
                    Edit ward
                </Button>
                <Button @click="addOpen = true">
                    <Plus class="size-4" />
                    Add beds
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Beds</p>
                <p class="mt-1 text-2xl font-semibold">{{ ward.total }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Occupied</p>
                <p class="mt-1 text-2xl font-semibold">{{ ward.occupied }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Available</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">
                    {{ ward.available }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Out of service</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ ward.out_of_service }}
                </p>
            </div>
        </div>

        <section>
            <h2 class="mb-3 text-sm font-semibold">Bed board</h2>
            <div
                v-if="!beds.length"
                class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
            >
                This ward has no beds yet.
            </div>
            <div
                v-else
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="bed in beds"
                    :key="bed.id"
                    class="flex flex-col rounded-xl border p-4"
                    :class="bedClass(bed.tone)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ bed.label }}</p>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="badgeClass(bed.tone)"
                            >{{ bed.status_label }}</span
                        >
                    </div>

                    <div v-if="bed.occupant" class="mt-3 flex-1">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                >{{ bed.occupant.initials }}</span
                            >
                            <div class="min-w-0">
                                <Link
                                    :href="bed.occupant.url"
                                    class="block truncate font-medium hover:underline"
                                    >{{ bed.occupant.name }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-mono">{{
                                        bed.occupant.file_number
                                    }}</span>
                                    · {{ bed.occupant.sex
                                    }}{{
                                        bed.occupant.age !== null
                                            ? ' · ' + bed.occupant.age + 'y'
                                            : ''
                                    }}
                                </p>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Admitted {{ bed.occupant.admitted_diff }}
                            <span v-if="bed.occupant.days">
                                · day {{ bed.occupant.days }}</span
                            >
                            <span v-if="bed.occupant.attending">
                                · {{ bed.occupant.attending }}</span
                            >
                        </p>
                    </div>
                    <div v-else class="mt-3 flex-1">
                        <p class="text-sm text-muted-foreground">
                            {{
                                bed.status === 'available'
                                    ? 'Free'
                                    : (bed.notes ?? 'Not in use')
                            }}
                        </p>
                    </div>

                    <div
                        v-if="bed.status !== 'occupied'"
                        class="mt-3 flex justify-end"
                    >
                        <Button
                            v-if="bed.status === 'available'"
                            size="sm"
                            variant="ghost"
                            class="text-xs"
                            :disabled="busyBed === bed.id"
                            @click="setBedStatus(bed, 'out_of_service')"
                        >
                            <Wrench class="size-3.5" />
                            Take out of service
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            variant="outline"
                            class="text-xs"
                            :disabled="busyBed === bed.id"
                            @click="setBedStatus(bed, 'available')"
                        >
                            Back in service
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add beds dialog -->
        <Dialog v-model:open="addOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Add beds</DialogTitle>
                    <DialogDescription>
                        New beds continue the ward's numbering.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitAdd">
                    <div class="grid gap-1.5">
                        <Label for="beds-count">How many *</Label>
                        <Input
                            id="beds-count"
                            v-model.number="addForm.count"
                            type="number"
                            min="1"
                            max="100"
                        />
                        <InputError :message="addForm.errors.count" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="beds-prefix">Label prefix</Label>
                        <Input id="beds-prefix" v-model="addForm.prefix" />
                        <InputError :message="addForm.errors.prefix" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="addOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="addForm.processing">
                            <Spinner v-if="addForm.processing" />
                            Add beds
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit ward dialog -->
        <Dialog v-model:open="editOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {{ ward.name }}</DialogTitle>
                    <DialogDescription>
                        An inactive ward keeps its beds and patients but takes
                        no new admissions.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="grid gap-3 sm:grid-cols-2"
                    @submit.prevent="submitEdit"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="edit-name">Ward name *</Label>
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
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in wardTypes"
                                    :key="t.value"
                                    :value="t.value"
                                    >{{ t.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="editForm.errors.type" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Daily bed charge</Label>
                        <Select v-model="editForm.bed_service_charge_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="No bed charge" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="c in bedCharges"
                                    :key="c.id"
                                    :value="String(c.id)"
                                    >{{ c.name }} ·
                                    {{ money(c.price) }}/day</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="editForm.errors.bed_service_charge_id"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="edit-description">Description</Label>
                        <Input
                            id="edit-description"
                            v-model="editForm.description"
                        />
                    </div>
                    <Label
                        class="flex items-center gap-2 text-sm font-normal sm:col-span-2"
                    >
                        <Checkbox v-model="editForm.is_active" />
                        Accepting admissions
                    </Label>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="editForm.processing">
                            <Spinner v-if="editForm.processing" />
                            Save ward
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
