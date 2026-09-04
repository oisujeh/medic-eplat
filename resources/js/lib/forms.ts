/**
 * Shared styling for the plain textareas used across clinical documentation.
 */
export const textareaClass =
    'w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60';

/**
 * Grow a textarea to fit its content as the user types.
 */
export function autoGrow(e: Event): void {
    const el = e.target as HTMLTextAreaElement;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

/**
 * Convert an ISO timestamp to the value a datetime-local input expects.
 */
export function toDatetimeLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * Toggle a value in a list in place.
 */
export function toggleInList(list: string[], value: string): void {
    const i = list.indexOf(value);

    if (i === -1) {
        list.push(value);
    } else {
        list.splice(i, 1);
    }
}
