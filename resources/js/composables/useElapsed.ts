import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * A live "started at / running for" pair for a timestamp, refreshed every
 * half minute.
 */
export function useElapsed(startedIso: string | null) {
    const startedAt = startedIso ? new Date(startedIso) : null;
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        timer = setInterval(() => (now.value = Date.now()), 30_000);
    });
    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    const startedLabel = computed(() =>
        startedAt
            ? startedAt.toLocaleTimeString(undefined, {
                  hour: '2-digit',
                  minute: '2-digit',
              })
            : null,
    );

    const durationLabel = computed(() => {
        if (!startedAt) {
            return null;
        }

        const mins = Math.max(
            0,
            Math.round((now.value - startedAt.getTime()) / 60000),
        );

        if (mins < 60) {
            return `${mins} min`;
        }

        const h = Math.floor(mins / 60);

        return `${h}h ${mins % 60}m`;
    });

    return { startedLabel, durationLabel };
}
