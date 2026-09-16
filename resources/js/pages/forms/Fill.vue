<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    CloudOff,
    LoaderCircle,
    Save,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import FormRenderer from '@/components/forms/FormRenderer.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { provideFormFill } from '@/composables/useFormFill';
import type { LabTestOption } from '@/composables/useFormFill';
import { textareaClass } from '@/lib/forms';
import type { Option, PatientBanner } from '@/types/clinical';
import type {
    FormContext,
    FormData,
    FormDefinition,
    FormEntryMode,
    FormSubmissionStatus,
} from '@/types/forms';

const props = defineProps<{
    submission: {
        id: number;
        status: FormSubmissionStatus;
        status_label: string;
        entry_mode: FormEntryMode;
        data: FormData;
        version: number;
        started_at_label: string;
        last_saved_at: string | null;
        filled_by: string | null;
        completed_at_label: string | null;
        completed_by: string | null;
        voided_at_label: string | null;
        voided_by: string | null;
        void_reason: string | null;
        urls: {
            save: string;
            complete: string;
            void: string;
            encounter: string | null;
        };
    };
    form: {
        id: number;
        slug: string;
        name: string;
        description: string | null;
    };
    definition: FormDefinition;
    context: FormContext;
    patient: PatientBanner;
    encounter: {
        id: number;
        type_label: string;
        service_point: string | null;
        is_open: boolean;
        date_label: string | null;
    } | null;
    entryModes: Option[];
    labTests: LabTestOption[];
    can: { fill: boolean; void: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Encounter', href: '#' },
            { title: 'Clinical form', href: '#' },
        ],
    },
});

const page = usePage();

const mode = ref<FormEntryMode>(props.submission.entry_mode);
const readonly = computed(() => !props.can.fill);

// Problems the server reported on the last completion attempt, keyed by
// field path ("data.weight" arrives as "weight").
const serverErrors = ref<Record<string, string>>({});

function readServerErrors() {
    const errors = (page.props.errors ?? {}) as Record<string, string>;
    const mapped: Record<string, string> = {};

    for (const [key, message] of Object.entries(errors)) {
        if (key.startsWith('data.')) {
            mapped[key.slice(5)] = message;
        }
    }

    serverErrors.value = mapped;
}

readServerErrors();
watch(() => page.props.errors, readServerErrors, { deep: true });

const fill = provideFormFill({
    definition: props.definition,
    initial: props.submission.data,
    context: props.context,
    readonly,
    labTests: props.labTests,
    serverErrors,
});

// ------------------------------------------------------------- Autosave

type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
const saveState = ref<SaveState>('idle');
const savedLabel = ref<string | null>(
    props.submission.last_saved_at
        ? new Date(props.submission.last_saved_at).toLocaleTimeString([], {
              hour: 'numeric',
              minute: '2-digit',
          })
        : null,
);
let timer: ReturnType<typeof setTimeout> | undefined;
let inFlight = false;
let queued = false;

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function flush(): Promise<void> {
    clearTimeout(timer);

    if (readonly.value) {
        return;
    }

    if (inFlight) {
        queued = true;

        return;
    }

    inFlight = true;
    saveState.value = 'saving';

    try {
        const response = await fetch(props.submission.urls.save, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ data: fill.data, entry_mode: mode.value }),
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const body = await response.json();
        savedLabel.value = body.saved_at_label ?? savedLabel.value;
        saveState.value = 'saved';
    } catch {
        saveState.value = 'error';
    } finally {
        inFlight = false;

        if (queued) {
            queued = false;
            void flush();
        }
    }
}

function scheduleSave() {
    if (readonly.value) {
        return;
    }

    saveState.value = 'dirty';
    clearTimeout(timer);
    timer = setTimeout(() => void flush(), 800);
}

watch(fill.data, scheduleSave, { deep: true });
watch(mode, scheduleSave);

function beforeUnload() {
    if (saveState.value === 'dirty') {
        void flush();
    }
}

onMounted(() => window.addEventListener('beforeunload', beforeUnload));
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);

    if (saveState.value === 'dirty') {
        void flush();
    }
});

// ------------------------------------------------------------- Complete

const renderer = ref<InstanceType<typeof FormRenderer> | null>(null);
const completing = ref(false);
const clientErrorCount = computed(
    () => Object.keys(fill.clientErrors.value).length,
);

type Payload = Parameters<typeof router.post>[1];

/** The answers as the server receives them: a plain, serialisable copy. */
function payload(): Payload {
    return JSON.parse(
        JSON.stringify({ data: fill.data, entry_mode: mode.value }),
    ) as Payload;
}

async function complete() {
    fill.showClientErrors.value = true;

    if (clientErrorCount.value > 0) {
        renderer.value?.goToFirstError(Object.keys(fill.clientErrors.value));

        return;
    }

    clearTimeout(timer);
    completing.value = true;

    router.post(props.submission.urls.complete, payload(), {
        preserveScroll: true,
        onError: (errors) => {
            const paths = Object.keys(errors)
                .filter((k) => k.startsWith('data.'))
                .map((k) => k.slice(5));
            renderer.value?.goToFirstError(paths);
        },
        onFinish: () => {
            completing.value = false;
        },
    });
}

// ----------------------------------------------------------------- Void

const voidOpen = ref(false);
const voidReason = ref('');
const voiding = ref(false);
const voidError = computed(
    () => ((page.props.errors ?? {}) as Record<string, string>).reason ?? null,
);

function voidSubmission() {
    voiding.value = true;

    router.post(
        props.submission.urls.void,
        { reason: voidReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                voidOpen.value = false;
            },
            onFinish: () => {
                voiding.value = false;
            },
        },
    );
}

const statusVariant = computed(() =>
    props.submission.status === 'completed'
        ? 'default'
        : props.submission.status === 'voided'
          ? 'destructive'
          : 'secondary',
);
</script>

<template>
    <Head :title="`${form.name} — ${patient.name}`" />

    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-4 p-4">
        <Link
            v-if="submission.urls.encounter"
            :href="submission.urls.encounter"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to encounter
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1
                    class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight"
                >
                    {{ form.name }}
                    <Badge :variant="statusVariant">{{
                        submission.status_label
                    }}</Badge>
                    <span
                        class="font-mono text-xs font-normal text-muted-foreground"
                        >v{{ submission.version }}</span
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <Link
                        :href="patient.url"
                        class="font-medium text-foreground hover:underline"
                        >{{ patient.name }}</Link
                    >
                    · {{ patient.file_number }}
                    <span v-if="patient.age !== null">
                        · {{ patient.age }}y</span
                    >
                    <span v-if="patient.sex_label">
                        · {{ patient.sex_label }}</span
                    >
                    <span v-if="encounter">
                        · {{ encounter.type_label
                        }}<span v-if="encounter.service_point"
                            >, {{ encounter.service_point }}</span
                        ><span v-if="encounter.date_label"
                            >, {{ encounter.date_label }}</span
                        ></span
                    >
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div
                    v-if="can.fill"
                    class="inline-flex rounded-md border border-border p-0.5"
                    role="radiogroup"
                    aria-label="Entry mode"
                >
                    <button
                        v-for="m in entryModes"
                        :key="m.value"
                        type="button"
                        role="radio"
                        :aria-checked="mode === m.value"
                        class="rounded px-2.5 py-1 text-xs transition-colors"
                        :class="
                            mode === m.value
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mode = m.value as FormEntryMode"
                    >
                        {{ m.value === 'guided' ? 'Guided' : 'Rapid' }}
                    </button>
                </div>
                <span
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                    aria-live="polite"
                >
                    <LoaderCircle
                        v-if="saveState === 'saving'"
                        class="size-3.5 animate-spin"
                    />
                    <CloudOff
                        v-else-if="saveState === 'error'"
                        class="size-3.5 text-destructive"
                    />
                    <CheckCircle2
                        v-else-if="saveState === 'saved' || savedLabel"
                        class="size-3.5 text-emerald-600"
                    />
                    <template v-if="saveState === 'saving'">Saving…</template>
                    <template v-else-if="saveState === 'dirty'"
                        >Unsaved changes</template
                    >
                    <template v-else-if="saveState === 'error'"
                        >Not saved — check the connection</template
                    >
                    <template v-else-if="savedLabel"
                        >Saved {{ savedLabel }}</template
                    >
                </span>
            </div>
        </header>

        <div
            v-if="submission.status === 'voided'"
            class="rounded-lg border border-red-500/30 bg-red-500/5 px-4 py-2 text-sm text-red-800 dark:text-red-300"
        >
            Voided
            <span v-if="submission.voided_by"
                >by {{ submission.voided_by }}</span
            >
            <span v-if="submission.voided_at_label"
                >on {{ submission.voided_at_label }}</span
            >:
            {{ submission.void_reason }}
        </div>
        <div
            v-else-if="!can.fill"
            class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-2 text-sm text-amber-800 dark:text-amber-300"
        >
            This form is read-only<template
                v-if="encounter && !encounter.is_open"
            >
                because the encounter has been signed</template
            >.
            <span v-if="submission.completed_by"
                >Completed by {{ submission.completed_by }} on
                {{ submission.completed_at_label }}.</span
            >
        </div>
        <div
            v-else-if="submission.status === 'completed'"
            class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 px-4 py-2 text-sm text-emerald-800 dark:text-emerald-300"
        >
            Completed by {{ submission.completed_by }} on
            {{ submission.completed_at_label }}. Changes are saved as you type;
            complete again to update the record.
        </div>

        <FormRenderer ref="renderer" :mode="mode" />

        <div
            v-if="fill.showClientErrors.value && clientErrorCount > 0"
            class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-2 text-sm text-destructive"
            role="alert"
        >
            {{ clientErrorCount }}
            {{
                clientErrorCount === 1 ? 'answer needs' : 'answers need'
            }}
            attention before the form can be completed.
        </div>

        <div
            v-if="can.fill || can.void"
            class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4"
        >
            <Button
                v-if="can.void"
                type="button"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                @click="voidOpen = true"
            >
                <Ban class="size-4" />
                Void form
            </Button>
            <span v-else />
            <Button
                v-if="can.fill"
                type="button"
                :disabled="completing"
                @click="complete"
            >
                <LoaderCircle v-if="completing" class="size-4 animate-spin" />
                <Save v-else class="size-4" />
                {{
                    submission.status === 'completed'
                        ? 'Save changes'
                        : 'Complete form'
                }}
            </Button>
        </div>

        <Dialog :open="voidOpen" @update:open="(v: boolean) => (voidOpen = v)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Void {{ form.name }}</DialogTitle>
                    <DialogDescription>
                        The answers stay on the audit trail but leave the
                        patient's record. Say why.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-1.5">
                    <Label for="void-reason">Reason</Label>
                    <textarea
                        id="void-reason"
                        v-model="voidReason"
                        rows="3"
                        :class="[textareaClass, 'resize-y overflow-auto']"
                        placeholder="e.g. Entered on the wrong patient"
                    />
                    <InputError :message="voidError ?? undefined" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="voidOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="voiding || voidReason.trim().length < 5"
                        @click="voidSubmission"
                    >
                        Void form
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
