<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { FlaskConical, Plus, Search, Trash2, X } from '@lucide/vue';
import { computed, ref, toRef } from 'vue';
import LabResultDialog from '@/components/clinical-record/LabResultDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLabCatalog } from '@/composables/useLabCatalog';
import { autoGrow, textareaClass } from '@/lib/forms';
import type { LabResult, LabTest } from '@/types/clinical';

/**
 * Order laboratory tests from the catalogue (or free text) and record results
 * for pending lines.
 */
const props = defineProps<{
    labResults: LabResult[];
    catalog: LabTest[];
    /** encounters.lab-orders.store */
    orderAction: string;
    /** encounters.lab-results.* base: `${resultsBase}/{id}` */
    resultsBase: string;
    disabled?: boolean;
}>();

const form = useForm<{
    lab_test_ids: number[];
    name: string;
    specimen: string;
    priority: string;
    clinical_details: string;
}>({
    lab_test_ids: [],
    name: '',
    specimen: '',
    priority: 'normal',
    clinical_details: '',
});

const selectedIds = toRef(form, 'lab_test_ids');
const { search, grouped, selected, isSelected, toggle } = useLabCatalog(
    props.catalog,
    selectedIds,
);

const resulted = computed(() =>
    props.labResults.filter((l) => l.status === 'resulted'),
);
const pending = computed(() =>
    props.labResults.filter((l) => l.status === 'pending'),
);

function order() {
    form.post(props.orderAction, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            search.value = '';
        },
    });
}

function cancelOrder(id: number) {
    router.delete(`${props.resultsBase}/${id}`, { preserveScroll: true });
}

const resulting = ref<LabResult | null>(null);

const flagClass = (flag: string | null) => {
    if (flag === 'critical') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400 ring-1 ring-red-500/30';
    }

    if (flag === 'high' || flag === 'low') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/30';
    }

    return 'text-foreground';
};
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-5">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold">
            <FlaskConical class="size-4 text-primary" />
            Laboratory
        </h2>
        <form
            v-if="!disabled"
            class="flex flex-col gap-4"
            @submit.prevent="order"
        >
            <div class="grid gap-1.5">
                <Label>Tests &amp; panels</Label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search the catalogue…"
                        class="pl-8"
                    />
                </div>
                <div v-if="selected.length" class="mt-1 flex flex-wrap gap-1.5">
                    <span
                        v-for="t in selected"
                        :key="t.id"
                        class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ t.name }}
                        <button
                            type="button"
                            aria-label="Remove"
                            @click="toggle(t.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </span>
                </div>
            </div>
            <div
                v-if="search"
                class="max-h-64 divide-y divide-border overflow-y-auto rounded-md border border-border"
            >
                <div v-for="group in grouped" :key="group.label">
                    <p
                        class="sticky top-0 bg-muted/70 px-3 py-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase backdrop-blur"
                    >
                        {{ group.label }}
                    </p>
                    <button
                        v-for="t in group.tests"
                        :key="t.id"
                        type="button"
                        class="flex w-full cursor-pointer items-start gap-2.5 px-3 py-2 text-left hover:bg-muted/40"
                        @click="toggle(t.id)"
                    >
                        <Checkbox
                            :model-value="isSelected(t.id)"
                            tabindex="-1"
                            class="pointer-events-none mt-0.5"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="text-sm font-medium text-foreground"
                                    >{{ t.name }}</span
                                >
                                <span
                                    v-if="t.is_panel"
                                    class="rounded bg-primary/10 px-1.5 text-[10px] font-semibold text-primary uppercase"
                                    >Panel · {{ t.component_count }}</span
                                >
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                <span class="font-mono">{{ t.code }}</span>
                                <span v-if="t.specimen"
                                    >· {{ t.specimen }}</span
                                >
                            </span>
                        </span>
                    </button>
                </div>
                <p
                    v-if="!grouped.length"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    No tests match your search.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Priority</Label>
                    <Select v-model="form.priority">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="normal">Routine</SelectItem>
                            <SelectItem value="urgent">Urgent</SelectItem>
                            <SelectItem value="emergency">STAT</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Other test (not in catalogue)</Label>
                    <Input
                        v-model="form.name"
                        placeholder="e.g. Special assay"
                    />
                    <InputError :message="form.errors.name" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label>Clinical details / indication</Label>
                <textarea
                    v-model="form.clinical_details"
                    :class="textareaClass"
                    rows="2"
                    placeholder="e.g. Fever, ?malaria"
                    @input="autoGrow"
                />
            </div>
            <div>
                <Button
                    type="submit"
                    :disabled="
                        form.processing ||
                        (!form.lab_test_ids.length && !form.name)
                    "
                >
                    <Plus class="size-4" />
                    Order test{{
                        form.lab_test_ids.length
                            ? ` (${form.lab_test_ids.length})`
                            : ''
                    }}
                </Button>
            </div>
        </form>
        <div
            v-if="pending.length || resulted.length"
            :class="disabled ? '' : 'mt-4 border-t border-border pt-3'"
        >
            <ul class="flex flex-col divide-y divide-border">
                <li
                    v-for="l in pending"
                    :key="l.id"
                    class="flex items-center justify-between gap-2 py-2"
                >
                    <span class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-foreground">{{ l.name }}</span>
                        <span
                            class="rounded bg-amber-500/10 px-1.5 text-[11px] text-amber-700 dark:text-amber-400"
                            >pending</span
                        >
                    </span>
                    <div v-if="!disabled" class="flex items-center gap-0.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground hover:text-foreground"
                            @click="resulting = l"
                        >
                            Record result
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                            aria-label="Cancel order"
                            @click="cancelOrder(l.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </li>
                <li
                    v-for="l in resulted"
                    :key="l.id"
                    class="flex items-center justify-between gap-2 py-2 text-sm"
                >
                    <span class="text-muted-foreground">{{ l.name }}</span>
                    <span
                        class="shrink-0 rounded px-1.5 font-medium"
                        :class="flagClass(l.flag)"
                        >{{ l.display_value }}</span
                    >
                </li>
            </ul>
        </div>
        <p v-else-if="disabled" class="text-sm text-muted-foreground">
            No laboratory orders.
        </p>

        <LabResultDialog
            :result="resulting"
            :results-base="resultsBase"
            @close="resulting = null"
        />
    </div>
</template>
