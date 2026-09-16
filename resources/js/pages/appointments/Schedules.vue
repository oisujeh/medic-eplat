<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarOff, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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

type Option = { id: number; name: string };
type Schedule = {
    id: number;
    provider_id: number;
    service_point_id: number | null;
    service_point: string | null;
    weekday: number;
    start_time: string;
    end_time: string;
    slot_minutes: number;
    is_active: boolean;
};
type Block = {
    id: number;
    provider_id: number;
    starts_at: string;
    ends_at: string;
    starts_label: string;
    ends_label: string;
    reason: string | null;
};

const props = defineProps<{
    providers: Option[];
    schedules: Schedule[];
    blocks: Block[];
    servicePoints: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Appointments', href: '/appointments' },
            { title: 'Provider schedules', href: '/appointments/schedules' },
        ],
    },
});

const weekdays = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];
const weekdayLabel = (w: number) =>
    weekdays.find((d) => d.value === w)?.label ?? '';

const selectedProviderId = ref<string>(
    props.providers.length ? String(props.providers[0].id) : '',
);
const selectedProvider = computed(() =>
    props.providers.find((p) => String(p.id) === selectedProviderId.value),
);

const providerSchedules = computed(() =>
    props.schedules
        .filter((s) => String(s.provider_id) === selectedProviderId.value)
        .sort(
            (a, b) =>
                a.weekday - b.weekday ||
                a.start_time.localeCompare(b.start_time),
        ),
);
const providerBlocks = computed(() =>
    props.blocks.filter(
        (b) => String(b.provider_id) === selectedProviderId.value,
    ),
);

// --- Availability row form ---
const scheduleForm = useForm({
    provider_id: '',
    service_point_id: '',
    weekday: '1',
    start_time: '09:00',
    end_time: '16:00',
    slot_minutes: 30,
});

function addSchedule() {
    scheduleForm
        .transform((d) => ({
            ...d,
            provider_id: Number(selectedProviderId.value),
            service_point_id: d.service_point_id
                ? Number(d.service_point_id)
                : null,
            weekday: Number(d.weekday),
        }))
        .post('/appointments/schedules', {
            preserveScroll: true,
            onSuccess: () => scheduleForm.reset('start_time', 'end_time'),
        });
}

function deleteSchedule(id: number) {
    router.delete(`/appointments/schedules/${id}`, { preserveScroll: true });
}

// --- Time-off block form ---
const blockForm = useForm({
    starts_at: '',
    ends_at: '',
    reason: '',
});

function addBlock() {
    blockForm
        .transform((d) => ({
            ...d,
            provider_id: Number(selectedProviderId.value),
        }))
        .post('/appointments/blocks', {
            preserveScroll: true,
            onSuccess: () => blockForm.reset(),
        });
}

function deleteBlock(id: number) {
    router.delete(`/appointments/blocks/${id}`, { preserveScroll: true });
}

const durations = [10, 15, 20, 30, 45, 60];
</script>

<template>
    <Head title="Provider schedules" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-4 p-4">
        <Link
            href="/appointments"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to calendar
        </Link>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Provider schedules
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Set each provider's weekly availability and time off. Bookable
                slots on the calendar are generated from these.
            </p>
        </div>

        <div class="grid gap-1.5 sm:max-w-xs">
            <Label>Provider</Label>
            <Select v-model="selectedProviderId">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select provider" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="p in providers"
                        :key="p.id"
                        :value="String(p.id)"
                        >{{ p.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <template v-if="selectedProvider">
            <!-- Weekly availability -->
            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="mb-4 text-base font-semibold">
                    Weekly availability — {{ selectedProvider.name }}
                </h2>

                <ul
                    v-if="providerSchedules.length"
                    class="mb-4 flex flex-col divide-y divide-border"
                >
                    <li
                        v-for="s in providerSchedules"
                        :key="s.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                    >
                        <span class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="w-24 font-medium">{{
                                weekdayLabel(s.weekday)
                            }}</span>
                            <span>{{ s.start_time }} – {{ s.end_time }}</span>
                            <span
                                class="rounded bg-muted px-1.5 text-[11px] text-muted-foreground"
                                >{{ s.slot_minutes }} min slots</span
                            >
                            <span
                                v-if="s.service_point"
                                class="rounded bg-primary/10 px-1.5 text-[11px] text-primary"
                                >{{ s.service_point }}</span
                            >
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                            aria-label="Remove"
                            @click="deleteSchedule(s.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                </ul>
                <p v-else class="mb-4 text-sm text-muted-foreground">
                    No availability set — this provider can't be booked into
                    slots yet.
                </p>

                <form
                    class="grid gap-3 border-t border-border pt-4 sm:grid-cols-6"
                    @submit.prevent="addSchedule"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Day</Label>
                        <Select v-model="scheduleForm.weekday">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="d in weekdays"
                                    :key="d.value"
                                    :value="String(d.value)"
                                    >{{ d.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>From</Label>
                        <Input v-model="scheduleForm.start_time" type="time" />
                        <InputError :message="scheduleForm.errors.start_time" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>To</Label>
                        <Input v-model="scheduleForm.end_time" type="time" />
                        <InputError :message="scheduleForm.errors.end_time" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Slot</Label>
                        <Select
                            :model-value="String(scheduleForm.slot_minutes)"
                            @update:model-value="
                                (v) => (scheduleForm.slot_minutes = Number(v))
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="d in durations"
                                    :key="d"
                                    :value="String(d)"
                                    >{{ d }}m</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end">
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="scheduleForm.processing"
                        >
                            <Plus class="size-4" />
                            Add
                        </Button>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label>Clinic (optional)</Label>
                        <Select v-model="scheduleForm.service_point_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Any clinic" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Any clinic</SelectItem>
                                <SelectItem
                                    v-for="s in servicePoints"
                                    :key="s.id"
                                    :value="String(s.id)"
                                    >{{ s.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </form>
            </section>

            <!-- Time off -->
            <section class="rounded-xl border border-border bg-card p-5">
                <h2
                    class="mb-4 flex items-center gap-2 text-base font-semibold"
                >
                    <CalendarOff class="size-4 text-primary" />
                    Time off
                </h2>

                <ul
                    v-if="providerBlocks.length"
                    class="mb-4 flex flex-col divide-y divide-border"
                >
                    <li
                        v-for="b in providerBlocks"
                        :key="b.id"
                        class="flex items-center justify-between gap-2 py-2.5 text-sm"
                    >
                        <span>
                            {{ b.starts_label }} – {{ b.ends_label }}
                            <span
                                v-if="b.reason"
                                class="ml-1 text-muted-foreground"
                                >· {{ b.reason }}</span
                            >
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                            aria-label="Remove"
                            @click="deleteBlock(b.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                </ul>
                <p v-else class="mb-4 text-sm text-muted-foreground">
                    No time off recorded.
                </p>

                <form
                    class="grid gap-3 border-t border-border pt-4 sm:grid-cols-3"
                    @submit.prevent="addBlock"
                >
                    <div class="grid gap-1.5">
                        <Label>From</Label>
                        <Input
                            v-model="blockForm.starts_at"
                            type="datetime-local"
                        />
                        <InputError :message="blockForm.errors.starts_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>To</Label>
                        <Input
                            v-model="blockForm.ends_at"
                            type="datetime-local"
                        />
                        <InputError :message="blockForm.errors.ends_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Reason (optional)</Label>
                        <Input
                            v-model="blockForm.reason"
                            placeholder="e.g. Leave"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <Button type="submit" :disabled="blockForm.processing">
                            <Plus class="size-4" />
                            Add time off
                        </Button>
                    </div>
                </form>
            </section>
        </template>
        <p v-else class="text-sm text-muted-foreground">
            No providers available.
        </p>
    </div>
</template>
