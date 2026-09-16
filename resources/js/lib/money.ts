/**
 * Format a naira amount for display, e.g. ₦4,400.00.
 */
export function naira(value: number, fractionDigits = 2): string {
    return (
        '₦' +
        Number(value ?? 0).toLocaleString('en-NG', {
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
        })
    );
}
