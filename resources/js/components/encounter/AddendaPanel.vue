<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FilePlus2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { autoGrow, textareaClass } from '@/lib/forms';
import type { EncounterAddendum } from '@/types/clinical';

/**
 * Notes appended to a signed encounter. The signed narrative stays as it
 * was; each addendum carries its own author and time.
 */
const props = defineProps<{
    addenda: EncounterAddendum[];
    /** encounters.addenda.store */
    action: string;
    canAddend: boolean;
}>();

const form = useForm({ body: '' });

function submit() {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <section
        v-if="addenda.length || canAddend"
        class="rounded-xl border border-border bg-card p-5"
    >
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
            <FilePlus2 class="size-4 text-primary" />
            Addenda
            <span
                v-if="addenda.length"
                class="font-normal text-muted-foreground"
                >({{ addenda.length }})</span
            >
        </h2>

        <ol v-if="addenda.length" class="flex flex-col divide-y divide-border">
            <li
                v-for="a in addenda"
                :key="a.id"
                class="py-3 first:pt-0 last:pb-0"
            >
                <p class="text-sm whitespace-pre-line">{{ a.body }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ a.author ?? 'Unknown author' }} ·
                    {{ a.recorded_at_label }}
                </p>
            </li>
        </ol>
        <p v-else class="text-sm text-muted-foreground">No addenda.</p>

        <form
            v-if="canAddend"
            class="mt-4 grid gap-2 border-t border-border pt-4"
            @submit.prevent="submit"
        >
            <Label for="addendum-body">Add an addendum</Label>
            <p class="text-xs text-muted-foreground">
                The signed note cannot be edited. Record corrections, late
                results or follow-up findings here; your name and the time are
                attached.
            </p>
            <textarea
                id="addendum-body"
                v-model="form.body"
                rows="3"
                :class="textareaClass"
                placeholder="e.g. Malaria RDT returned positive after sign-off; ACT started."
                @input="autoGrow"
            />
            <InputError :message="form.errors.body" />
            <div>
                <Button
                    type="submit"
                    :disabled="form.processing || !form.body.trim()"
                >
                    <FilePlus2 class="size-4" />
                    Add addendum
                </Button>
            </div>
        </form>
    </section>
</template>
