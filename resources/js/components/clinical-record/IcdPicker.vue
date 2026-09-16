<script setup lang="ts">
import { Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useIcdSearch } from '@/composables/useIcdSearch';
import type { IcdMatch } from '@/composables/useIcdSearch';

/**
 * A diagnosis search box backed by the ICD-10 catalogue. Emits the picked
 * entry; the typed text is the description.
 */
const props = defineProps<{ modelValue: string; disabled?: boolean }>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
    pick: [match: IcdMatch];
}>();

const query = ref(props.modelValue);
watch(
    () => props.modelValue,
    (v) => {
        query.value = v;
    },
);
watch(query, (v) => emit('update:modelValue', v));

const { results, open, close } = useIcdSearch(query);

function pick(match: IcdMatch) {
    close();
    query.value = match.description;
    emit('pick', match);
}
</script>

<template>
    <div class="relative min-w-52 flex-1">
        <Search
            class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="query"
            placeholder="Search ICD-10 diagnosis…"
            class="pl-8"
            autocomplete="off"
            :disabled="disabled"
            @focus="open = results.length > 0"
            @keydown.escape="open = false"
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
                <span class="font-mono text-xs text-primary">{{ m.code }}</span>
                <span class="ml-2">{{ m.description }}</span>
                <span
                    v-if="m.chapter"
                    class="ml-2 text-xs text-muted-foreground"
                    >· {{ m.chapter }}</span
                >
            </li>
        </ul>
    </div>
</template>
