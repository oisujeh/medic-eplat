import type { ObservationSet } from '@/types/clinical';

/**
 * Tailwind classes for a chip at a given severity level.
 */
export function chipClass(level: string): string {
    if (level === 'critical') {
        return 'bg-red-500/10 text-red-700 dark:text-red-400 ring-1 ring-red-500/30';
    }

    if (level === 'warning') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/30';
    }

    return 'bg-muted';
}

/**
 * Text colour for a value at a given severity level.
 */
export function levelTextClass(level: string): string {
    if (level === 'critical') {
        return 'text-red-600 dark:text-red-400';
    }

    if (level === 'warning') {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-foreground';
}

/**
 * Classes + label for the overall alert badge on a set of readings.
 */
export function alertBadge(
    level: string,
): { class: string; label: string } | null {
    if (level === 'critical') {
        return {
            class: 'bg-red-500/15 text-red-700 dark:text-red-400',
            label: 'Critical',
        };
    }

    if (level === 'warning') {
        return {
            class: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
            label: 'Review',
        };
    }

    return null;
}

/**
 * A numeric reading from a set, or null when it was not taken.
 */
export function numeric(
    set: ObservationSet | null,
    code: string,
): number | null {
    const value = set?.values[code];

    return typeof value === 'number' ? value : null;
}

export type ClinicalScore = {
    label: string;
    value: string;
    detail: string | null;
    level: string; // 'normal' | 'warning' | 'critical'
};

/**
 * Find which band a value falls into. `ranges` are inclusive [min, max, points]
 * tuples; returns the band's points, or null when the value is missing.
 */
function band(
    value: number | null,
    ranges: Array<[number, number, number]>,
): number | null {
    if (value === null) {
        return null;
    }

    for (const [min, max, points] of ranges) {
        if (value >= min && value <= max) {
            return points;
        }
    }

    return null;
}

/**
 * Body Mass Index — the stored reading, otherwise derived from weight and
 * height. Returns null when neither is available.
 */
export function bmiScore(set: ObservationSet | null): ClinicalScore | null {
    let bmi = numeric(set, 'bmi');
    const weight = numeric(set, 'weight');
    const height = numeric(set, 'height');

    if (bmi === null && weight && height && height > 0) {
        const m = height / 100;
        bmi = weight / (m * m);
    }

    if (bmi === null) {
        return null;
    }

    let detail: string;
    let level: string;

    if (bmi < 18.5) {
        detail = 'Underweight';
        level = 'warning';
    } else if (bmi < 25) {
        detail = 'Normal weight';
        level = 'normal';
    } else if (bmi < 30) {
        detail = 'Overweight';
        level = 'warning';
    } else {
        detail = 'Obese';
        level = 'critical';
    }

    return {
        label: 'BMI',
        value: (Math.round(bmi * 10) / 10).toFixed(1),
        detail,
        level,
    };
}

/**
 * NEWS2 (National Early Warning Score 2) aggregated from the parameters we
 * capture. Consciousness (ACVPU) and supplemental-oxygen scoring are not
 * recorded here, so this assumes an alert patient breathing room air — treat it
 * as a screening aid, not a substitute for the full score.
 */
export function news2Score(set: ObservationSet | null): ClinicalScore | null {
    let total = 0;
    let measured = 0;
    let redFlag = false; // any single parameter scoring 3

    const contribute = (points: number | null) => {
        if (points === null) {
            return;
        }

        measured += 1;
        total += points;

        if (points === 3) {
            redFlag = true;
        }
    };

    contribute(
        band(numeric(set, 'respiratory_rate'), [
            [-Infinity, 8, 3],
            [9, 11, 1],
            [12, 20, 0],
            [21, 24, 2],
            [25, Infinity, 3],
        ]),
    );
    contribute(
        band(numeric(set, 'spo2'), [
            [-Infinity, 91, 3],
            [92, 93, 2],
            [94, 95, 1],
            [96, Infinity, 0],
        ]),
    );
    contribute(
        band(numeric(set, 'systolic_bp'), [
            [-Infinity, 90, 3],
            [91, 100, 2],
            [101, 110, 1],
            [111, 219, 0],
            [220, Infinity, 3],
        ]),
    );
    contribute(
        band(numeric(set, 'pulse'), [
            [-Infinity, 40, 3],
            [41, 50, 1],
            [51, 90, 0],
            [91, 110, 1],
            [111, 130, 2],
            [131, Infinity, 3],
        ]),
    );
    contribute(
        band(numeric(set, 'temperature'), [
            [-Infinity, 35, 3],
            [35.1, 36, 1],
            [36.1, 38, 0],
            [38.1, 39, 1],
            [39.1, Infinity, 2],
        ]),
    );

    if (measured === 0) {
        return null;
    }

    let detail: string;
    let level: string;

    if (total >= 7) {
        detail = 'High risk';
        level = 'critical';
    } else if (total >= 5) {
        detail = 'Medium risk';
        level = 'warning';
    } else if (redFlag) {
        detail = 'Low–medium risk';
        level = 'warning';
    } else {
        detail = 'Low risk';
        level = 'normal';
    }

    return { label: 'NEWS2', value: String(total), detail, level };
}
