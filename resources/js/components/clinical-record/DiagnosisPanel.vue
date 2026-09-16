<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import IcdPicker from '@/components/clinical-record/IcdPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { IcdMatch } from '@/composables/useIcdSearch';
import type { Problem } from '@/types/clinical';

/**
 * Coded diagnoses for the encounter: primary, secondary and differential
 * lines on the patient's problem list.
 */
const props = defineProps<{
    problems: Problem[];
    /** encounters.problems.store */
    action: string;
    disabled?: boolean;
}>();

const form = useForm<{
    name: string;
    code: string;
    status: string;
    role: string | null;
}>({
    name: '',
    code: '',
    status: 'active',
    role: null,
});

function pick(match: IcdMatch) {
    form.name = match.description;
    form.code = match.code;
}

function add(role: string) {
    if (!form.name) {
        return;
    }

    form.status = 'active';
    form.role = role;
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
}

function remove(id: number) {
    router.delete(`${props.action}/${id}`, { preserveScroll: true });
}

const groups = computed(() => [
    {
        label: 'Primary diagnosis',
        items: props.problems.filter((p) => p.role === 'primary'),
    },
    {
        label: 'Secondary diagnosis',
        items: props.problems.filter((p) => p.role === 'secondary'),
    },
    {
        label: 'Differential diagnoses',
        items: props.problems.filter((p) => p.role === 'differential'),
    },
]);
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-5">
        <h2 class="mb-4 text-base font-semibold">Diagnosis</h2>
        <div class="flex flex-col gap-3">
            <div v-if="!disabled" class="flex flex-wrap gap-2">
                <IcdPicker v-model="form.name" @pick="pick" />
                <Input
                    v-model="form.code"
                    placeholder="ICD-10 code"
                    class="w-32"
                />
            </div>
            <div v-if="!disabled" class="flex flex-wrap gap-2">
                <Button
                    v-for="role in ['primary', 'secondary', 'differential']"
                    :key="role"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="capitalize"
                    :disabled="form.processing || !form.name"
                    @click="add(role)"
                >
                    <Plus class="size-4" />
                    {{ role }}
                </Button>
            </div>
            <InputError :message="form.errors.name" />
            <InputError :message="form.errors.role" />

            <dl class="mt-1 grid gap-3">
                <div v-for="group in groups" :key="group.label">
                    <dt
                        class="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.label }}
                    </dt>
                    <dd
                        v-if="group.items.length"
                        class="flex flex-wrap gap-1.5"
                    >
                        <span
                            v-for="p in group.items"
                            :key="p.id"
                            class="inline-flex items-center gap-1.5 rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                        >
                            {{ p.name }}
                            <span
                                v-if="p.code"
                                class="font-mono text-primary/70"
                                >{{ p.code }}</span
                            >
                            <button
                                v-if="!disabled"
                                type="button"
                                aria-label="Remove diagnosis"
                                @click="remove(p.id)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                    </dd>
                    <dd v-else class="text-xs text-muted-foreground">
                        None recorded.
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>
