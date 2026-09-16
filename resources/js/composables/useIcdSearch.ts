import { ref, watch } from 'vue';
import type { Ref } from 'vue';

export type IcdMatch = {
    id: number;
    code: string;
    description: string;
    chapter: string | null;
};

/**
 * Debounced ICD-10 lookup against the catalogue for a typed query.
 */
export function useIcdSearch(query: Ref<string>, url = '/clinical/icd-search') {
    const results = ref<IcdMatch[]>([]);
    const open = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(query, (q) => {
        clearTimeout(timer);

        if (q.trim().length < 2) {
            results.value = [];
            open.value = false;

            return;
        }

        timer = setTimeout(async () => {
            const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });
            results.value = (await res.json()).codes ?? [];
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
