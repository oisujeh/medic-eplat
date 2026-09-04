<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
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
import type { Allergy } from '@/types/clinical';

const props = defineProps<{
    open: boolean;
    allergies: Allergy[];
    /** encounters.allergies.store */
    action: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    substance: '',
    category: '',
    reaction: '',
    severity: '',
});

function add() {
    form.transform((d) => ({
        ...d,
        category: d.category || null,
        severity: d.severity || null,
        reaction: d.reaction || null,
    })).post(props.action, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onFinish: () => form.transform((d) => d),
    });
}

function remove(id: number) {
    router.delete(`${props.action}/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v: boolean) => emit('update:open', v)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Allergies</DialogTitle>
            </DialogHeader>

            <ul
                v-if="allergies.length"
                class="flex flex-col divide-y divide-border"
            >
                <li
                    v-for="a in allergies"
                    :key="a.id"
                    class="flex items-center justify-between gap-2 py-2"
                >
                    <span class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-medium text-foreground">{{
                            a.substance
                        }}</span>
                        <span
                            v-if="a.reaction"
                            class="text-xs text-muted-foreground"
                            >{{ a.reaction }}</span
                        >
                        <span
                            v-if="a.severity"
                            class="rounded px-1.5 text-[11px] capitalize"
                            :class="
                                a.severity === 'severe'
                                    ? 'bg-red-500/10 text-red-700 dark:text-red-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ a.severity }}</span
                        >
                    </span>
                    <Button
                        v-if="!disabled"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-muted-foreground hover:text-red-600 dark:hover:text-red-400"
                        aria-label="Remove allergy"
                        @click="remove(a.id)"
                    >
                        <X class="size-4" />
                    </Button>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No allergies recorded.
            </p>

            <form
                v-if="!disabled"
                class="grid gap-3 border-t border-border pt-4 sm:grid-cols-2"
                @submit.prevent="add"
            >
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label>Substance *</Label>
                    <Input
                        v-model="form.substance"
                        placeholder="e.g. Penicillin"
                    />
                    <InputError :message="form.errors.substance" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Category</Label>
                    <Select v-model="form.category">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="—" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="drug">Drug</SelectItem>
                            <SelectItem value="food">Food</SelectItem>
                            <SelectItem value="environmental"
                                >Environmental</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Severity</Label>
                    <Select v-model="form.severity">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="—" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="mild">Mild</SelectItem>
                            <SelectItem value="moderate">Moderate</SelectItem>
                            <SelectItem value="severe">Severe</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.severity" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label>Reaction</Label>
                    <Input
                        v-model="form.reaction"
                        placeholder="e.g. Rash, anaphylaxis"
                    />
                </div>
                <div class="sm:col-span-2">
                    <Button type="submit" :disabled="form.processing">
                        <Plus class="size-4" />
                        Add allergy
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
