import { ref, watch } from 'vue';
import type { Ref } from 'vue';

export type ConceptMatch = {
    id: number;
    name: string;
    class: string;
    unit: string | null;
    codes: string[];
    synonyms: string[];
};

/**
 * Debounced concept lookup against the terminology service for a typed
 * query, within one concept class (drug, allergen, vaccine…).
 */
export function useConceptSearch(
    query: Ref<string>,
    conceptClass: string,
    url = '/terminology/search',
) {
    const results = ref<ConceptMatch[]>([]);
    const open = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let latest = 0;

    watch(query, (q) => {
        clearTimeout(timer);

        if (q.trim().length < 2) {
            results.value = [];
            open.value = false;

            return;
        }

        timer = setTimeout(async () => {
            const request = ++latest;
            const params = new URLSearchParams({ class: conceptClass, q });
            const res = await fetch(`${url}?${params}`, {
                headers: { Accept: 'application/json' },
            });

            if (request !== latest || !res.ok) {
                return;
            }

            results.value = (await res.json()).concepts ?? [];
            open.value = results.value.length > 0;
        }, 200);
    });

    function close() {
        clearTimeout(timer);
        results.value = [];
        open.value = false;
    }

    return { results, open, close };
}
