import { computed, ref } from 'vue';
import type { Ref } from 'vue';
import type { LabTest } from '@/types/clinical';

/**
 * Search and selection over the laboratory catalogue for the order picker.
 */
export function useLabCatalog(catalog: LabTest[], selectedIds: Ref<number[]>) {
    const search = ref('');

    const grouped = computed(() => {
        const q = search.value.trim().toLowerCase();
        const matches = catalog.filter(
            (t) =>
                !q ||
                t.name.toLowerCase().includes(q) ||
                t.code.toLowerCase().includes(q),
        );
        const groups = new Map<string, LabTest[]>();

        for (const t of matches) {
            const list = groups.get(t.department_label) ?? [];
            list.push(t);
            groups.set(t.department_label, list);
        }

        return [...groups.entries()]
            .map(([label, tests]) => ({
                label,
                tests: [...tests].sort(
                    (a, b) => Number(b.is_panel) - Number(a.is_panel),
                ),
            }))
            .sort((a, b) => a.label.localeCompare(b.label));
    });

    const selected = computed(() =>
        catalog.filter((t) => selectedIds.value.includes(t.id)),
    );

    function isSelected(id: number): boolean {
        return selectedIds.value.includes(id);
    }

    function toggle(id: number) {
        const i = selectedIds.value.indexOf(id);

        if (i === -1) {
            selectedIds.value.push(id);
        } else {
            selectedIds.value.splice(i, 1);
        }
    }

    return { search, grouped, selected, isSelected, toggle };
}
