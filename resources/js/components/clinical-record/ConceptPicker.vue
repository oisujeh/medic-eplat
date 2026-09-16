<script setup lang="ts">
import { Check, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useConceptSearch } from '@/composables/useConceptSearch';
import type { ConceptMatch } from '@/composables/useConceptSearch';

/**
 * A name box backed by the terminology service. Picking a concept codes the
 * entry; typing anything else keeps the text and drops the code, so free
 * text always remains possible.
 */
const props = defineProps<{
    modelValue: string;
    conceptId: number | null;
    conceptClass: 'drug' | 'allergen' | 'vaccine';
    placeholder?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:conceptId': [value: number | null];
}>();

const query = ref(props.modelValue);
let pickedName: string | null = null;

watch(
    () => props.modelValue,
    (v) => {
        query.value = v;
    },
);

watch(query, (v) => {
    emit('update:modelValue', v);

    // Editing away from the picked name uncodes the entry.
    if (pickedName !== null && v !== pickedName) {
        pickedName = null;
        emit('update:conceptId', null);
    }
});

const { results, open, close } = useConceptSearch(query, props.conceptClass);

function pick(match: ConceptMatch) {
    close();
    pickedName = match.name;
    query.value = match.name;
    emit('update:conceptId', match.id);
}
</script>

<template>
    <div class="relative">
        <Search
            class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="query"
            :placeholder="placeholder"
            class="pr-8 pl-8"
            autocomplete="off"
            :disabled="disabled"
            @focus="open = results.length > 0"
            @keydown.escape="open = false"
        />
        <Check
            v-if="conceptId !== null"
            class="absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-emerald-600"
            aria-label="Coded"
        />
        <ul
            v-if="open"
            class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover text-sm shadow-md"
        >
            <li
                v-for="m in results"
                :key="m.id"
                class="cursor-pointer px-3 py-2 hover:bg-muted"
                @mousedown.prevent="pick(m)"
            >
                <span>{{ m.name }}</span>
                <span
                    v-if="m.synonyms.length"
                    class="ml-2 text-xs text-muted-foreground"
                    >{{ m.synonyms.slice(0, 3).join(', ') }}</span
                >
                <span
                    v-if="m.codes.length"
                    class="ml-2 font-mono text-[11px] text-primary"
                    >{{ m.codes[0] }}</span
                >
            </li>
        </ul>
    </div>
</template>
