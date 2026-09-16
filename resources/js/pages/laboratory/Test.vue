<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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

type Band = {
    id: number;
    sex: string | null;
    sex_label: string;
    age_min_days: number;
    age_max_days: number | null;
    age_label: string;
    low: number | null;
    high: number | null;
    critical_low: number | null;
    critical_high: number | null;
    text: string | null;
};

const props = defineProps<{
    test: {
        id: number;
        code: string;
        name: string;
        department_label: string;
        unit: string | null;
        specimen_type: string | null;
        is_panel: boolean;
        reference: string | null;
        reference_low: number | null;
        reference_high: number | null;
        reference_text: string | null;
    };
    ranges: Band[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Laboratory', href: '/laboratory' },
            { title: 'Test catalogue', href: '/laboratory/catalogue' },
            { title: 'Test', href: '#' },
        ],
    },
});

const adding = ref(false);

// Ages are typed in years or months at the desk and stored in days, so
// neonatal bands work alongside adult ones.
const ageUnits = [
    { value: 'days', label: 'days', factor: 1 },
    { value: 'months', label: 'months', factor: 30 },
    { value: 'years', label: 'years', factor: 365 },
];
const ageUnit = ref('years');
const ageFrom = ref('0');
const ageTo = ref('');

const form = useForm({
    sex: 'any',
    age_min_days: 0,
    age_max_days: null as number | null,
    low: '',
    high: '',
    critical_low: '',
    critical_high: '',
    text: '',
});

function toDays(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    const factor = ageUnits.find((u) => u.value === ageUnit.value)?.factor ?? 1;

    return Math.round(Number(value) * factor);
}

function submit() {
    form.transform((data) => ({
        ...data,
        sex: data.sex === 'any' ? null : data.sex,
        age_min_days: toDays(ageFrom.value) ?? 0,
        age_max_days: toDays(ageTo.value),
        low: data.low === '' ? null : data.low,
        high: data.high === '' ? null : data.high,
        critical_low: data.critical_low === '' ? null : data.critical_low,
        critical_high: data.critical_high === '' ? null : data.critical_high,
        text: data.text === '' ? null : data.text,
    })).post(`/laboratory/catalogue/${props.test.id}/ranges`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            ageFrom.value = '0';
            ageTo.value = '';
            adding.value = false;
        },
        onFinish: () => form.transform((d) => d),
    });
}

function remove(band: Band) {
    router.delete(`/laboratory/catalogue/${props.test.id}/ranges/${band.id}`, {
        preserveScroll: true,
    });
}

function limit(v: number | null): string {
    return v === null ? '—' : String(v);
}
</script>

<template>
    <Head :title="`${test.name} reference ranges`" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex items-start gap-3">
            <Button as-child variant="ghost" size="icon" class="mt-0.5">
                <Link
                    href="/laboratory/catalogue"
                    aria-label="Back to catalogue"
                >
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ test.name }}
                    <span
                        class="ml-2 font-mono text-base text-muted-foreground"
                        >{{ test.code }}</span
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ test.department_label
                    }}<template v-if="test.specimen_type">
                        · {{ test.specimen_type }}</template
                    ><template v-if="test.unit">
                        · reported in {{ test.unit }}</template
                    >
                </p>
            </div>
        </div>

        <section class="rounded-xl border border-border bg-card p-5">
            <h2 class="text-sm font-semibold">Facility-wide range</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Used when no band below matches the patient:
                <span class="font-medium text-foreground">{{
                    test.reference ?? 'none set'
                }}</span>
            </p>
        </section>

        <section class="rounded-xl border border-border bg-card p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold">Bands by age and sex</h2>
                    <p class="text-xs text-muted-foreground">
                        The most specific band that fits the patient is copied
                        onto each result line when a test is ordered. Critical
                        limits raise a critical flag.
                    </p>
                </div>
                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="adding = !adding"
                >
                    <Plus class="size-4" />
                    Add band
                </Button>
            </div>

            <div v-if="ranges.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="py-2 pr-3 font-medium">Sex</th>
                            <th class="py-2 pr-3 font-medium">Age</th>
                            <th class="py-2 pr-3 font-medium">Low</th>
                            <th class="py-2 pr-3 font-medium">High</th>
                            <th class="py-2 pr-3 font-medium">Critical low</th>
                            <th class="py-2 pr-3 font-medium">Critical high</th>
                            <th class="py-2 pr-3 font-medium">Text</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="b in ranges" :key="b.id">
                            <td class="py-2 pr-3">{{ b.sex_label }}</td>
                            <td class="py-2 pr-3">{{ b.age_label }}</td>
                            <td class="py-2 pr-3 tabular-nums">
                                {{ limit(b.low) }}
                            </td>
                            <td class="py-2 pr-3 tabular-nums">
                                {{ limit(b.high) }}
                            </td>
                            <td
                                class="py-2 pr-3 text-red-700 tabular-nums dark:text-red-400"
                            >
                                {{ limit(b.critical_low) }}
                            </td>
                            <td
                                class="py-2 pr-3 text-red-700 tabular-nums dark:text-red-400"
                            >
                                {{ limit(b.critical_high) }}
                            </td>
                            <td class="py-2 pr-3 text-muted-foreground">
                                {{ b.text ?? '—' }}
                            </td>
                            <td class="py-2 text-right">
                                <Button
                                    v-if="canManage"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove band"
                                    @click="remove(b)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No bands yet. Every patient gets the facility-wide range.
            </p>

            <form
                v-if="adding && canManage"
                class="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5">
                    <Label for="band-sex">Sex</Label>
                    <Select v-model="form.sex">
                        <SelectTrigger id="band-sex" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="any">Any</SelectItem>
                            <SelectItem value="F">Female</SelectItem>
                            <SelectItem value="M">Male</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.sex" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-age-from">Age from</Label>
                    <Input
                        id="band-age-from"
                        v-model="ageFrom"
                        type="number"
                        min="0"
                        step="any"
                    />
                    <InputError :message="form.errors.age_min_days" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-age-to">Age to (blank: no limit)</Label>
                    <Input
                        id="band-age-to"
                        v-model="ageTo"
                        type="number"
                        min="0"
                        step="any"
                    />
                    <InputError :message="form.errors.age_max_days" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-age-unit">Age unit</Label>
                    <Select v-model="ageUnit">
                        <SelectTrigger id="band-age-unit" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="u in ageUnits"
                                :key="u.value"
                                :value="u.value"
                                >{{ u.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-low">Low</Label>
                    <Input
                        id="band-low"
                        v-model="form.low"
                        type="number"
                        step="any"
                    />
                    <InputError :message="form.errors.low" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-high">High</Label>
                    <Input
                        id="band-high"
                        v-model="form.high"
                        type="number"
                        step="any"
                    />
                    <InputError :message="form.errors.high" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-critical-low">Critical low</Label>
                    <Input
                        id="band-critical-low"
                        v-model="form.critical_low"
                        type="number"
                        step="any"
                    />
                    <InputError :message="form.errors.critical_low" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="band-critical-high">Critical high</Label>
                    <Input
                        id="band-critical-high"
                        v-model="form.critical_high"
                        type="number"
                        step="any"
                    />
                    <InputError :message="form.errors.critical_high" />
                </div>
                <div class="grid gap-1.5 sm:col-span-4">
                    <Label for="band-text"
                        >Qualitative reference (optional)</Label
                    >
                    <Input
                        id="band-text"
                        v-model="form.text"
                        placeholder="e.g. Negative"
                    />
                    <InputError :message="form.errors.text" />
                </div>
                <div class="flex items-center gap-2 sm:col-span-4">
                    <Button type="submit" size="sm" :disabled="form.processing">
                        Save band
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="adding = false"
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
