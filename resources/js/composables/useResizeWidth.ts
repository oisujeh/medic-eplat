import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

/**
 * Track the pixel width of an element reactively so SVG charts can render with
 * crisp, real coordinates (rather than a distorting viewBox). Returns a width
 * that starts at a sensible default until the element is measured on mount.
 */
export function useResizeWidth(
    el: Ref<HTMLElement | null>,
    fallback = 640,
): Ref<number> {
    const width = ref(fallback);
    let observer: ResizeObserver | null = null;

    onMounted(() => {
        if (!el.value || typeof ResizeObserver === 'undefined') {
            return;
        }
        observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const w = entry.contentRect.width;
                if (w > 0) {
                    width.value = w;
                }
            }
        });
        observer.observe(el.value);
        width.value = el.value.clientWidth || fallback;
    });

    onBeforeUnmount(() => observer?.disconnect());

    return width;
}
