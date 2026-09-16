<script setup lang="ts">
import { computed, ref } from 'vue';
import FormRenderer from '@/components/forms/FormRenderer.vue';
import { Button } from '@/components/ui/button';
import { provideFormFill } from '@/composables/useFormFill';
import type { FormContext, FormDefinition, FormEntryMode } from '@/types/forms';

/**
 * The designer's live preview: the definition as it stands, filled with
 * throwaway answers for a sample patient (PRD FRM-10). Re-mounted by its
 * parent whenever the definition changes.
 */
const props = defineProps<{
    definition: FormDefinition;
    context: FormContext;
    mode: FormEntryMode;
}>();

const fill = provideFormFill({
    definition: props.definition,
    initial: {},
    context: props.context,
    readonly: ref(false),
});

const problems = computed(() => Object.keys(fill.clientErrors.value).length);

function check() {
    fill.showClientErrors.value = true;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <FormRenderer :mode="mode" />
        <div
            class="flex items-center justify-between gap-2 border-t border-border pt-3"
        >
            <span class="text-xs text-muted-foreground">
                <template v-if="fill.showClientErrors.value">
                    {{ problems }}
                    {{ problems === 1 ? 'problem' : 'problems' }} with the
                    sample answers
                </template>
                <template v-else
                    >Fill the preview to try skip logic, calculations and
                    rules.</template
                >
            </span>
            <Button type="button" variant="outline" size="sm" @click="check"
                >Check answers</Button
            >
        </div>
    </div>
</template>
