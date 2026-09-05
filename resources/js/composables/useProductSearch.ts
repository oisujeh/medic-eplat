import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { StockProduct } from '@/types/clinical';

/**
 * The stocked products a prescription can be written for: those of the
 * chosen drug concept, or, when the drug is not coded, those whose name
 * contains what was typed. Debounced, with stale responses dropped.
 */
export function useProductSearch(
    conceptId: Ref<number | null>,
    name: Ref<string>,
    url = '/products/search',
) {
    const products = ref<StockProduct[]>([]);
    const loading = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let latest = 0;

    async function load() {
        const request = ++latest;
        const params = new URLSearchParams();

        if (conceptId.value !== null) {
            params.set('concept_id', String(conceptId.value));
        } else if (name.value.trim().length >= 2) {
            params.set('q', name.value.trim());
        } else {
            products.value = [];

            return;
        }

        loading.value = true;

        try {
            const res = await fetch(`${url}?${params}`, {
                headers: { Accept: 'application/json' },
            });

            if (request !== latest || !res.ok) {
                return;
            }

            products.value = (await res.json()).products ?? [];
        } finally {
            if (request === latest) {
                loading.value = false;
            }
        }
    }

    watch([conceptId, name], () => {
        clearTimeout(timer);
        timer = setTimeout(load, 200);
    });

    return { products, loading, reload: load };
}
