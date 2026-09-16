/**
 * A categorical palette chosen to stay legible and distinct in both light and
 * dark themes. Used by the composition charts (donuts, multi-series bars).
 */
export const CHART_PALETTE = [
    '#6366f1', // indigo
    '#10b981', // emerald
    '#f59e0b', // amber
    '#0ea5e9', // sky
    '#ec4899', // pink
    '#8b5cf6', // violet
    '#14b8a6', // teal
    '#f43f5e', // rose
];

/** The primary accent used for single-series trend charts. */
export const CHART_PRIMARY = '#6366f1';

/**
 * Resolve a colour for a series index, wrapping around the palette.
 */
export function paletteColor(index: number): string {
    return CHART_PALETTE[index % CHART_PALETTE.length];
}
