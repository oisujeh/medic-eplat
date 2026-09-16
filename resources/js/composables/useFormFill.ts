import { computed, inject, provide, reactive, ref, watch } from 'vue';
import type { ComputedRef, InjectionKey, Ref } from 'vue';
import { FormEvaluator, withShape } from '@/lib/formEngine';
import type {
    FormContext,
    FormData,
    FormDefinition,
    FormInstance,
} from '@/types/forms';

export type LabTestOption = { id: number; code: string; name: string };

export type FormFill = {
    definition: FormDefinition;
    /** The answers, reactive; inputs write here through setValue(). */
    data: FormData;
    context: Partial<FormContext>;
    evaluator: FormEvaluator;
    readonly: Ref<boolean>;
    locale: string;
    labTests: LabTestOption[];
    /** Problems from the server (after a completion attempt), by path. */
    serverErrors: Ref<Record<string, string>>;
    /** Problems the client finds, shown once the user asks to complete. */
    clientErrors: ComputedRef<Record<string, string>>;
    showClientErrors: Ref<boolean>;
    errorFor: (path: string) => string | null;
    setValue: (scope: FormInstance | null, key: string, value: unknown) => void;
};

const key: InjectionKey<FormFill> = Symbol('form-fill');

/**
 * Own the answers of one form being filled (or previewed) and share them
 * with the renderer and its inputs. Calculated fields refresh on every
 * change; the parent watches `data` to autosave.
 */
export function provideFormFill(options: {
    definition: FormDefinition;
    initial: FormData;
    context: Partial<FormContext>;
    readonly: Ref<boolean>;
    locale?: string;
    labTests?: LabTestOption[];
    serverErrors?: Ref<Record<string, string>>;
}): FormFill {
    const data = reactive(
        withShape(options.definition, options.initial),
    ) as FormData;
    const evaluator = new FormEvaluator(
        options.definition,
        data,
        options.context,
    );
    const serverErrors =
        options.serverErrors ?? ref<Record<string, string>>({});
    const showClientErrors = ref(false);
    const clientErrors = computed(() => evaluator.errors());

    evaluator.compute();
    watch(data, () => evaluator.compute(), { deep: true });

    const fill: FormFill = {
        definition: options.definition,
        data,
        context: options.context,
        evaluator,
        readonly: options.readonly,
        locale: options.locale ?? 'en',
        labTests: options.labTests ?? [],
        serverErrors,
        clientErrors,
        showClientErrors,
        errorFor: (path) =>
            serverErrors.value[path] ??
            (showClientErrors.value
                ? (clientErrors.value[path] ?? null)
                : null),
        setValue: (scope, name, value) => {
            if (options.readonly.value) {
                return;
            }

            if (scope) {
                scope[name] = value;
            } else {
                data[name] = value;
            }

            // A corrected answer clears what the server said about it.
            for (const path of Object.keys(serverErrors.value)) {
                if (path === name || path.endsWith(`.${name}`)) {
                    delete serverErrors.value[path];
                }
            }
        },
    };

    provide(key, fill);

    return fill;
}

export function useFormFill(): FormFill {
    const fill = inject(key);

    if (!fill) {
        throw new Error('useFormFill() must be used inside a form renderer.');
    }

    return fill;
}
