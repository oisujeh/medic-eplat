export type VitalFlag = { metric: string; level: string; label: string };

export type Vitals = {
    id: number;
    temperature_c: number | null;
    blood_pressure: string | null;
    systolic_bp: number | null;
    diastolic_bp: number | null;
    pulse_bpm: number | null;
    respiratory_rate: number | null;
    spo2: number | null;
    blood_glucose: number | null;
    pain_score: number | null;
    weight_kg: number | null;
    height_cm: number | null;
    bmi: number | null;
    muac_cm: number | null;
    head_circumference_cm: number | null;
    notes: string | null;
    recorded_by: string | null;
    recorded_at: string | null;
    recorded_at_diff: string | null;
    recorded_at_short: string | null;
    alert_level: string;
    flags: VitalFlag[];
};

export type VitalChip = {
    label: string;
    value: string;
    level: string; // 'normal' | 'warning' | 'critical'
    reason: string | null;
};

/**
 * Build a compact list of recorded measurements (skipping any that are blank),
 * each carrying its clinical severity so abnormal values can be coloured.
 */
export function vitalsChips(v: Vitals): VitalChip[] {
    // metric key(s) behind each displayed chip, used to look up its flag
    const worstFor = (...metrics: string[]): VitalFlag | null => {
        const matches = v.flags.filter((f) => metrics.includes(f.metric));
        if (matches.some((f) => f.level === 'critical'))
            return matches.find((f) => f.level === 'critical') ?? null;
        return matches[0] ?? null;
    };

    const chips: VitalChip[] = [];
    const push = (
        label: string,
        value: number | string | null,
        unit: string,
        ...metrics: string[]
    ) => {
        if (value === null || value === undefined || value === '') return;
        const flag = worstFor(...metrics);
        chips.push({
            label,
            value: `${value}${unit}`,
            level: flag?.level ?? 'normal',
            reason: flag?.label ?? null,
        });
    };

    push('Temp', v.temperature_c, '°C', 'temperature_c');
    push('BP', v.blood_pressure, ' mmHg', 'systolic_bp', 'diastolic_bp');
    push('Pulse', v.pulse_bpm, ' bpm', 'pulse_bpm');
    push('Resp', v.respiratory_rate, ' /min', 'respiratory_rate');
    push('SpO₂', v.spo2, '%', 'spo2');
    push('Glucose', v.blood_glucose, ' mmol/L', 'blood_glucose');
    push('Pain', v.pain_score === null ? null : `${v.pain_score}/10`, '', 'pain_score');
    push('Weight', v.weight_kg, ' kg', 'weight_kg');
    push('Height', v.height_cm, ' cm', 'height_cm');
    push('BMI', v.bmi, '', 'bmi');
    push('MUAC', v.muac_cm, ' cm', 'muac_cm');
    push('Head circ.', v.head_circumference_cm, ' cm', 'head_circumference_cm');

    return chips;
}

/**
 * Tailwind classes for a chip at a given severity level.
 */
export function chipClass(level: string): string {
    if (level === 'critical')
        return 'bg-red-500/10 text-red-700 dark:text-red-400 ring-1 ring-red-500/30';
    if (level === 'warning')
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/30';
    return 'bg-muted';
}

/**
 * Classes + label for the overall alert badge on a reading.
 */
export function alertBadge(
    level: string,
): { class: string; label: string } | null {
    if (level === 'critical')
        return {
            class: 'bg-red-500/15 text-red-700 dark:text-red-400',
            label: 'Critical',
        };
    if (level === 'warning')
        return {
            class: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
            label: 'Review',
        };
    return null;
}
