import type {
    FormCalculation,
    FormCondition,
    FormContext,
    FormData,
    FormDefinition,
    FormField,
    FormInstance,
    FormLabel,
    FormPage,
    FormSection,
} from '@/types/forms';

/**
 * The client half of the forms engine: the same skip logic, calculations
 * and checks the server applies (app/Support/Forms), so the screen can show
 * and hide, compute and warn as the user types. The server remains the
 * authority when a form is completed.
 */

type Lookup = (kind: 'field' | 'context', name: string) => unknown;

export function labelOf(
    entity: { label?: FormLabel; title?: FormLabel; key?: string } | undefined,
    locale = 'en',
    attribute:
        'label' | 'title' | 'help' | 'placeholder' | 'description' = 'label',
): string {
    if (!entity) {
        return '';
    }

    const raw =
        (entity as Record<string, unknown>)[attribute] ??
        entity.title ??
        entity.key ??
        '';

    if (raw && typeof raw === 'object') {
        const map = raw as Record<string, string>;

        return map[locale] ?? map.en ?? Object.values(map)[0] ?? '';
    }

    return String(raw ?? '');
}

export function isEmpty(value: unknown): boolean {
    return (
        value === null ||
        value === undefined ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    );
}

export function isRepeating(section: FormSection): boolean {
    return !!section.repeat && typeof section.repeat === 'object';
}

// ------------------------------------------------------------ Conditions

function truthy(value: unknown): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'string') {
        return ['1', 'true', 'yes', 'y'].includes(value.toLowerCase());
    }

    return !!value;
}

function isNumeric(value: unknown): boolean {
    return (
        (typeof value === 'number' && !Number.isNaN(value)) ||
        (typeof value === 'string' &&
            value.trim() !== '' &&
            !Number.isNaN(Number(value)))
    );
}

function equals(a: unknown, b: unknown): boolean {
    if (isEmpty(a) && isEmpty(b)) {
        return true;
    }

    if (typeof a === 'boolean' || typeof b === 'boolean') {
        return truthy(a) === truthy(b);
    }

    if (isNumeric(a) && isNumeric(b)) {
        return Number(a) === Number(b);
    }

    if (Array.isArray(a) || Array.isArray(b)) {
        return JSON.stringify(a) === JSON.stringify(b);
    }

    return String(a) === String(b);
}

function ordered(a: unknown, b: unknown): number | null {
    if (isEmpty(a) || isEmpty(b)) {
        return null;
    }

    if (isNumeric(a) && isNumeric(b)) {
        return Math.sign(Number(a) - Number(b));
    }

    if (typeof a === 'string' && typeof b === 'string') {
        return a < b ? -1 : a > b ? 1 : 0;
    }

    return null;
}

function operand(value: unknown, lookup: Lookup): unknown {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        const keys = Object.keys(value);

        if (keys.length === 1 && keys[0] === 'field') {
            return lookup('field', String((value as { field: string }).field));
        }

        if (keys.length === 1 && keys[0] === 'context') {
            return lookup(
                'context',
                String((value as { context: string }).context),
            );
        }
    }

    return value;
}

export function evaluateCondition(
    condition: FormCondition | null | undefined,
    lookup: Lookup,
): boolean {
    if (condition === null || condition === undefined) {
        return true;
    }

    if (typeof condition !== 'object') {
        return !!condition;
    }

    if ('all' in condition) {
        return (condition.all ?? []).every((c) => evaluateCondition(c, lookup));
    }

    if ('any' in condition) {
        return (condition.any ?? []).some((c) => evaluateCondition(c, lookup));
    }

    if ('not' in condition) {
        return !evaluateCondition(condition.not, lookup);
    }

    if (!('field' in condition) || !condition.op) {
        return false;
    }

    const actual = lookup('field', condition.field);
    const expected = operand(condition.value, lookup);

    switch (condition.op) {
        case 'empty':
            return isEmpty(actual);
        case 'not_empty':
            return !isEmpty(actual);
        case 'eq':
            return equals(actual, expected);
        case 'ne':
            return !equals(actual, expected);
        case 'gt':
            return ordered(actual, expected) === 1;
        case 'gte':
            return [0, 1].includes(ordered(actual, expected) ?? -9);
        case 'lt':
            return ordered(actual, expected) === -1;
        case 'lte':
            return [0, -1].includes(ordered(actual, expected) ?? 9);
        case 'in':
            return (
                Array.isArray(expected) &&
                expected.some((e) => equals(actual, e))
            );
        case 'not_in':
            return (
                Array.isArray(expected) &&
                !expected.some((e) => equals(actual, e))
            );
        case 'contains':
            if (Array.isArray(actual)) {
                return actual.some((a) => equals(a, expected));
            }

            return (
                typeof actual === 'string' &&
                expected !== '' &&
                expected !== null &&
                expected !== undefined &&
                actual.toLowerCase().includes(String(expected).toLowerCase())
            );
        default:
            return false;
    }
}

// ---------------------------------------------------------- Calculations

function toNumber(value: unknown): number | null {
    if (typeof value === 'number') {
        return Number.isNaN(value) ? null : value;
    }

    if (typeof value === 'boolean') {
        return value ? 1 : 0;
    }

    return isNumeric(value) ? Number(value) : null;
}

function toMoment(expr: unknown, lookup: Lookup): Date | null {
    let value = expr;

    if (expr && typeof expr === 'object' && 'field' in (expr as object)) {
        value = lookup('field', String((expr as { field: string }).field));
    } else if (
        expr &&
        typeof expr === 'object' &&
        'context' in (expr as object)
    ) {
        value = lookup(
            'context',
            String((expr as { context: string }).context),
        );
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function evaluateCalculation(
    expression: FormCalculation | FormCondition | null | undefined,
    lookup: Lookup,
): number | null {
    if (expression === null || expression === undefined) {
        return null;
    }

    if (typeof expression === 'number') {
        return expression;
    }

    if (typeof expression === 'string') {
        return toNumber(expression);
    }

    if (typeof expression !== 'object') {
        return null;
    }

    if ('field' in expression) {
        return toNumber(lookup('field', String(expression.field)));
    }

    if ('context' in expression) {
        return toNumber(lookup('context', String(expression.context)));
    }

    if (!('op' in expression)) {
        return null;
    }

    const op = expression.op;
    const args = Array.isArray(expression.args) ? expression.args : [];

    if (op === 'if') {
        const branch = evaluateCondition(args[0] as FormCondition, lookup)
            ? args[1]
            : args[2];

        return evaluateCalculation(branch as FormCalculation, lookup);
    }

    if (['minutes_between', 'days_between', 'years_between'].includes(op)) {
        const from = toMoment(args[0], lookup);
        const to = toMoment(args[1], lookup);

        if (!from || !to) {
            return null;
        }

        const ms = to.getTime() - from.getTime();
        const round2 = (n: number) => Math.round(n * 100) / 100;

        if (op === 'minutes_between') {
            return round2(ms / 60000);
        }

        if (op === 'days_between') {
            return round2(ms / 86400000);
        }

        return round2(ms / (365.25 * 86400000));
    }

    const values: number[] = [];

    for (const arg of args) {
        const value = evaluateCalculation(arg as FormCalculation, lookup);

        if (value === null) {
            return null;
        }

        values.push(value);
    }

    switch (op) {
        case '+':
            return values.reduce((a, b) => a + b, 0);
        case '-':
            return values.length === 1
                ? -values[0]
                : values.slice(1).reduce((a, b) => a - b, values[0]);
        case '*':
            return values.reduce((a, b) => a * b, 1);
        case '/': {
            let acc = values[0];

            for (const v of values.slice(1)) {
                if (v === 0) {
                    return null;
                }

                acc = acc / v;
            }

            return values.length ? acc : null;
        }
        case '^':
            return values.length === 2 ? values[0] ** values[1] : null;
        case 'round': {
            if (!values.length) {
                return null;
            }

            const digits = Math.trunc(values[1] ?? 0);
            const factor = 10 ** digits;

            return Math.round(values[0] * factor) / factor;
        }
        case 'abs':
            return values.length === 1 ? Math.abs(values[0]) : null;
        case 'min':
            return values.length ? Math.min(...values) : null;
        case 'max':
            return values.length ? Math.max(...values) : null;
        default:
            return null;
    }
}

// ------------------------------------------------------------- Evaluator

type IndexEntry = { field: FormField; section: FormSection; page: FormPage };

/**
 * A definition applied to a set of answers: visibility, calculated values,
 * problems and the empty shape of a new repeat.
 */
export class FormEvaluator {
    private index = new Map<string, IndexEntry>();
    private resolving = new Set<string>();

    constructor(
        public readonly definition: FormDefinition,
        public readonly data: FormData,
        public readonly context: Partial<FormContext> = {},
    ) {
        for (const page of definition.pages ?? []) {
            for (const section of page.sections ?? []) {
                for (const field of section.fields ?? []) {
                    this.index.set(field.key, { field, section, page });
                }
            }
        }
    }

    lookup(scope: FormInstance | null): Lookup {
        return (kind, name) => {
            if (kind === 'context') {
                return (this.context as Record<string, unknown>)[name] ?? null;
            }

            const value =
                scope && Object.prototype.hasOwnProperty.call(scope, name)
                    ? scope[name]
                    : this.data[name];

            const entry = this.index.get(name);

            if (!entry || this.resolving.has(name)) {
                return value ?? null;
            }

            this.resolving.add(name);

            try {
                const shown =
                    this.isVisible(entry.page, null) &&
                    this.isVisible(entry.section, null) &&
                    this.isVisible(entry.field, scope);

                return shown ? (value ?? null) : null;
            } finally {
                this.resolving.delete(name);
            }
        };
    }

    isVisible(
        entity: { show_when?: FormCondition },
        scope: FormInstance | null,
    ): boolean {
        return evaluateCondition(entity.show_when, this.lookup(scope));
    }

    /** Fill every calculated field from the current answers, in place. */
    compute(): void {
        for (const page of this.definition.pages ?? []) {
            for (const section of page.sections ?? []) {
                const calculated = (section.fields ?? []).filter(
                    (f) => f.type === 'calculated',
                );

                if (!calculated.length) {
                    continue;
                }

                if (isRepeating(section)) {
                    const instances = this.instances(section);

                    for (const instance of instances) {
                        for (const field of calculated) {
                            instance[field.key] = evaluateCalculation(
                                field.calc,
                                this.lookup(instance),
                            );
                        }
                    }
                } else {
                    for (const field of calculated) {
                        this.data[field.key] = evaluateCalculation(
                            field.calc,
                            this.lookup(null),
                        );
                    }
                }
            }
        }
    }

    instances(section: FormSection): FormInstance[] {
        const list = this.data[section.key];

        return Array.isArray(list) ? (list as FormInstance[]) : [];
    }

    /**
     * What is wrong, keyed by path ("weight", "specimens.1.site"), the
     * same way the server reports it.
     */
    errors(): Record<string, string> {
        const errors: Record<string, string> = {};

        for (const page of this.definition.pages ?? []) {
            if (!this.isVisible(page, null)) {
                continue;
            }

            for (const section of page.sections ?? []) {
                if (!this.isVisible(section, null)) {
                    continue;
                }

                if (isRepeating(section)) {
                    const instances = this.instances(section);
                    const min = section.repeat?.min ?? 0;
                    const max = section.repeat?.max;
                    const title = labelOf(section, 'en', 'title');

                    if (instances.length < min) {
                        errors[section.key] =
                            `${title} needs at least ${min} ${min === 1 ? 'entry' : 'entries'}.`;
                    }

                    if (
                        max !== undefined &&
                        max !== null &&
                        instances.length > max
                    ) {
                        errors[section.key] =
                            `${title} allows at most ${max} entries.`;
                    }

                    instances.forEach((instance, i) => {
                        for (const field of section.fields ?? []) {
                            const problem = this.check(field, instance);

                            if (problem) {
                                errors[`${section.key}.${i}.${field.key}`] =
                                    problem;
                            }
                        }
                    });

                    continue;
                }

                for (const field of section.fields ?? []) {
                    const problem = this.check(field, null);

                    if (problem) {
                        errors[field.key] = problem;
                    }
                }
            }
        }

        return errors;
    }

    private check(field: FormField, scope: FormInstance | null): string | null {
        if (field.type === 'calculated' || !this.isVisible(field, scope)) {
            return null;
        }

        const value = scope ? scope[field.key] : this.data[field.key];
        const label = labelOf(field);

        if (isEmpty(value)) {
            return field.required ? `${label} is required.` : null;
        }

        const typed = this.typeProblem(field, value, label);

        if (typed) {
            return typed;
        }

        for (const rule of field.validate ?? []) {
            if (evaluateCondition(rule.when, this.lookup(scope))) {
                return rule.message || `${label} is not valid.`;
            }
        }

        return null;
    }

    private typeProblem(
        field: FormField,
        value: unknown,
        label: string,
    ): string | null {
        const unit = field.unit ? ` ${field.unit}` : '';

        switch (field.type) {
            case 'numeric': {
                if (!isNumeric(value)) {
                    return `${label} must be a number.`;
                }

                const n = Number(value);

                if (field.integer && !Number.isInteger(n)) {
                    return `${label} must be a whole number.`;
                }

                if (
                    field.min !== undefined &&
                    field.min !== null &&
                    n < field.min
                ) {
                    return `${label} must be at least ${field.min}${unit}.`;
                }

                if (
                    field.max !== undefined &&
                    field.max !== null &&
                    n > field.max
                ) {
                    return `${label} must be at most ${field.max}${unit}.`;
                }

                return null;
            }
            case 'coded': {
                const allowed = (field.options ?? []).map((o) =>
                    String(o.value),
                );
                const chosen = field.multiple
                    ? Array.isArray(value)
                        ? value
                        : [value]
                    : [value];

                return chosen.every((c) => allowed.includes(String(c)))
                    ? null
                    : `${label} has a choice that is not on the list.`;
            }
            case 'text':
                if (
                    field.max_length &&
                    typeof value === 'string' &&
                    value.length > field.max_length
                ) {
                    return `${label} must be ${field.max_length} characters or fewer.`;
                }

                return null;
            case 'date':
            case 'datetime': {
                const date =
                    typeof value === 'string' ? new Date(value) : new Date(NaN);

                if (Number.isNaN(date.getTime())) {
                    return `${label} must be a valid ${field.type === 'date' ? 'date' : 'date and time'}.`;
                }

                if (field.not_future && date.getTime() > Date.now()) {
                    return `${label} cannot be in the future.`;
                }

                return null;
            }
            case 'boolean':
                return typeof value === 'boolean'
                    ? null
                    : `${label} must be yes or no.`;
            case 'drug':
                return value && typeof value === 'object' && 'id' in value
                    ? null
                    : `${label} must be picked from the drug dictionary.`;
            case 'diagnosis':
                return value && typeof value === 'object' && 'code' in value
                    ? null
                    : `${label} must be an ICD-10 diagnosis.`;
            case 'lab_test':
                return value && typeof value === 'object' && 'id' in value
                    ? null
                    : `${label} must be picked from the test catalogue.`;
            default:
                return null;
        }
    }
}

/** The answers a new repeat of a section starts with. */
export function emptyInstance(section: FormSection): FormInstance {
    const instance: FormInstance = {};

    for (const field of section.fields ?? []) {
        if (field.type === 'calculated') {
            continue;
        }

        instance[field.key] =
            field.default !== undefined
                ? field.default
                : field.type === 'coded' && field.multiple
                  ? []
                  : null;
    }

    return instance;
}

/** Answers with every field present, so inputs bind to a defined key. */
export function withShape(
    definition: FormDefinition,
    data: FormData,
): FormData {
    const shaped: FormData = { ...data };

    for (const page of definition.pages ?? []) {
        for (const section of page.sections ?? []) {
            if (isRepeating(section)) {
                if (!Array.isArray(shaped[section.key])) {
                    shaped[section.key] = [];
                }

                continue;
            }

            for (const field of section.fields ?? []) {
                if (!(field.key in shaped)) {
                    shaped[field.key] =
                        field.default !== undefined
                            ? field.default
                            : field.type === 'coded' && field.multiple
                              ? []
                              : null;
                }
            }
        }
    }

    return shaped;
}
