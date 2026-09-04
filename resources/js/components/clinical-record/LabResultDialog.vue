<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { watch } from 'vue';
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
import type { LabResult } from '@/types/clinical';

const props = defineProps<{
    result: LabResult | null;
    /** encounters.lab-results.* base: `${resultsBase}/{id}` */
    resultsBase: string;
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    value: '',
    unit: '',
    reference_range: '',
    flag: 'normal',
});

watch(
    () => props.result,
    () => {
        form.reset();
        form.clearErrors();
    },
);

function submit() {
    if (!props.result) {
        return;
    }

    form.patch(`${props.resultsBase}/${props.result.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog
        :open="result !== null"
        @update:open="
            (v: boolean) => {
                if (!v) emit('close');
            }
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Record result — {{ result?.name }}</DialogTitle>
            </DialogHeader>
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label>Value *</Label>
                    <Input
                        v-model="form.value"
                        placeholder="e.g. 13.8 or Negative"
                    />
                    <InputError :message="form.errors.value" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Unit</Label>
                    <Input v-model="form.unit" placeholder="e.g. g/dL" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Reference range</Label>
                    <Input
                        v-model="form.reference_range"
                        placeholder="e.g. 13-17"
                    />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label>Flag</Label>
                    <Select v-model="form.flag">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="normal">Normal</SelectItem>
                            <SelectItem value="low">Low</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="sm:col-span-2">
                    <Button type="submit" :disabled="form.processing">
                        <CheckCircle2 class="size-4" />
                        Save result
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
