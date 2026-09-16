<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Download,
    Eye,
    Save,
    Send,
    Undo2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import FormPreview from '@/components/forms/FormPreview.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { textareaClass } from '@/lib/forms';
import type { Option } from '@/types/clinical';
import type { FormContext, FormDefinition, FormEntryMode } from '@/types/forms';

const props = defineProps<{
    form: {
        id: number;
        slug: string;
        name: string;
        description: string | null;
        status: 'draft' | 'published' | 'retired';
        status_label: string;
        entry_mode: FormEntryMode;
        encounter_types: string[];
        roles: string[];
        definition: FormDefinition;
        published_version: number | null;
        has_unpublished_changes: boolean;
        urls: {
            update: string;
            publish: string;
            retire: string;
            restore: string;
            export: string;
            index: string;
        };
    };
    issues: string[];
    versions: Array<{
        version: number;
        published_at_label: string;
        published_by: string | null;
        notes: string | null;
        submissions_count: number;
    }>;
    options: {
        encounterTypes: Option[];
        roles: Option[];
        entryModes: Option[];
        fieldTypes: Option[];
        conditionOperators: string[];
        calculationOperators: string[];
        contextKeys: string[];
    };
    sampleContext: FormContext;
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin/users' },
            { title: 'Clinical forms', href: '/admin/forms' },
            { title: 'Designer', href: '#' },
        ],
    },
});

const page = usePage();

// ------------------------------------------------------------ The draft

// The definition travels alongside the settings (see saveDraft); it is
// kept out of the form state because its recursive shape defeats the
// form helper's typing.
const settings = useForm({
    name: props.form.name,
    description: props.form.description ?? '',
    entry_mode: props.form.entry_mode,
    encounter_types: [...props.form.encounter_types],
    roles: [...props.form.roles],
});

const json = ref(JSON.stringify(props.form.definition, null, 2));
const parseError = ref<string | null>(null);
const parsed = ref<FormDefinition>(props.form.definition);

watch(json, (text) => {
    try {
        const value = JSON.parse(text);

        if (
            !value ||
            typeof value !== 'object' ||
            !Array.isArray(value.pages)
        ) {
            parseError.value =
                'The definition must be an object with a "pages" list.';

            return;
        }

        parsed.value = value as FormDefinition;
        parseError.value = null;
    } catch (e) {
        parseError.value = (e as Error).message;
    }
});

function toggle(list: string[], value: string) {
    const i = list.indexOf(value);

    if (i === -1) {
        list.push(value);
    } else {
        list.splice(i, 1);
    }
}

function saveDraft() {
    if (parseError.value) {
        return;
    }

    settings
        .transform((data) => ({ ...data, definition: parsed.value }))
        .patch(props.form.urls.update, {
            preserveScroll: true,
            onFinish: () => settings.transform((data) => data),
        });
}

const dirty = computed(
    () =>
        settings.isDirty ||
        JSON.stringify(parsed.value) !== JSON.stringify(props.form.definition),
);

// ------------------------------------------------------------- Publish

const publishOpen = ref(false);
const publish = useForm({ notes: '' });

// What the server said about the definition on the last save or publish.
const definitionError = computed(
    () =>
        ((page.props.errors ?? {}) as Record<string, string>).definition ??
        null,
);

function doPublish() {
    publish.post(props.form.urls.publish, {
        preserveScroll: true,
        onSuccess: () => {
            publishOpen.value = false;
            publish.reset();
        },
    });
}

function retire() {
    router.post(props.form.urls.retire, {}, { preserveScroll: true });
}

function restore() {
    router.post(props.form.urls.restore, {}, { preserveScroll: true });
}

// ------------------------------------------------------------- Preview

const previewMode = ref<FormEntryMode>(props.form.entry_mode);
const previewKey = ref(0);

// The preview re-mounts when the definition changes, so it always renders
// what the JSON says now.
watch(parsed, () => previewKey.value++);

const statusVariant = computed(() =>
    props.form.status === 'published'
        ? 'default'
        : props.form.status === 'retired'
          ? 'destructive'
          : 'secondary',
);
</script>

<template>
    <Head :title="`${form.name} — form designer`" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <AdminNav current="forms" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <Button as-child variant="ghost" size="icon" class="mt-0.5">
                    <Link :href="form.urls.index" aria-label="Back to forms">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div>
                    <h1
                        class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight"
                    >
                        {{ form.name }}
                        <Badge :variant="statusVariant">{{
                            form.status_label
                        }}</Badge>
                        <span
                            v-if="form.published_version"
                            class="font-mono text-xs font-normal text-muted-foreground"
                            >v{{ form.published_version }}</span
                        >
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="font-mono text-xs">{{ form.slug }}</span>
                        <span
                            v-if="
                                form.has_unpublished_changes &&
                                form.published_version
                            "
                        >
                            · the draft has changes not yet published</span
                        >
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline">
                    <a :href="form.urls.export">
                        <Download class="size-4" />
                        Export JSON
                    </a>
                </Button>
                <template v-if="canManage">
                    <Button
                        v-if="form.status === 'retired'"
                        variant="outline"
                        @click="restore"
                    >
                        <Undo2 class="size-4" />
                        Offer again
                    </Button>
                    <Button
                        v-else-if="form.status === 'published'"
                        variant="outline"
                        @click="retire"
                        >Retire</Button
                    >
                    <Button
                        variant="outline"
                        :disabled="settings.processing || !!parseError"
                        @click="saveDraft"
                    >
                        <Save class="size-4" />
                        Save draft
                    </Button>
                    <Button
                        :disabled="dirty || issues.length > 0"
                        :title="
                            dirty
                                ? 'Save the draft first'
                                : issues.length
                                  ? 'Fix the issues first'
                                  : undefined
                        "
                        @click="publishOpen = true"
                    >
                        <Send class="size-4" />
                        Publish
                    </Button>
                </template>
            </div>
        </div>

        <div
            v-if="issues.length"
            class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-900 dark:text-amber-200"
        >
            <p class="mb-1 flex items-center gap-1.5 font-medium">
                <AlertTriangle class="size-4" />
                The saved draft cannot be published yet
            </p>
            <ul class="list-disc pl-5">
                <li v-for="issue in issues" :key="issue">{{ issue }}</li>
            </ul>
        </div>
        <InputError :message="definitionError ?? undefined" />

        <div class="grid gap-4 xl:grid-cols-2">
            <!-- Left: settings and definition -->
            <div class="flex flex-col gap-4">
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-base font-semibold">Settings</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="settings.name"
                                :disabled="!canManage"
                            />
                            <InputError :message="settings.errors.name" />
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <Label for="description">Description</Label>
                            <Input
                                id="description"
                                v-model="settings.description"
                                :disabled="!canManage"
                            />
                            <InputError
                                :message="settings.errors.description"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Default entry mode</Label>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="m in options.entryModes"
                                    :key="m.value"
                                    type="button"
                                    class="rounded-md border px-3 py-1.5 text-sm"
                                    :class="
                                        settings.entry_mode === m.value
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input hover:bg-muted'
                                    "
                                    :disabled="!canManage"
                                    @click="
                                        settings.entry_mode =
                                            m.value as FormEntryMode
                                    "
                                >
                                    {{ m.label }}
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Offered on encounter types</Label>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <label
                                    v-for="t in options.encounterTypes"
                                    :key="t.value"
                                    class="inline-flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        :model-value="
                                            settings.encounter_types.includes(
                                                t.value,
                                            )
                                        "
                                        :disabled="!canManage"
                                        @update:model-value="
                                            toggle(
                                                settings.encounter_types,
                                                t.value,
                                            )
                                        "
                                    />
                                    {{ t.label }}
                                </label>
                            </div>
                            <InputError
                                :message="settings.errors.encounter_types"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <Label>Who may fill it</Label>
                            <p class="text-xs text-muted-foreground">
                                Leave every role unticked to offer the form to
                                anyone who can document the encounter.
                            </p>
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <label
                                    v-for="r in options.roles"
                                    :key="r.value"
                                    class="inline-flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        :model-value="
                                            settings.roles.includes(r.value)
                                        "
                                        :disabled="!canManage"
                                        @update:model-value="
                                            toggle(settings.roles, r.value)
                                        "
                                    />
                                    {{ r.label }}
                                </label>
                            </div>
                            <InputError :message="settings.errors.roles" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-base font-semibold">Definition</h2>
                        <span class="text-xs text-muted-foreground"
                            >JSON · pages › sections › fields</span
                        >
                    </div>
                    <textarea
                        v-model="json"
                        rows="28"
                        spellcheck="false"
                        :class="[
                            textareaClass,
                            'resize-y overflow-auto font-mono text-xs leading-5',
                        ]"
                        :disabled="!canManage"
                        aria-label="Form definition JSON"
                    />
                    <p
                        v-if="parseError"
                        class="mt-1 text-xs text-destructive"
                        role="alert"
                    >
                        {{ parseError }}
                    </p>
                    <details class="mt-3 text-xs text-muted-foreground">
                        <summary class="cursor-pointer">Reference</summary>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <p>
                                <span class="font-medium text-foreground"
                                    >Field types:</span
                                >
                                {{
                                    options.fieldTypes
                                        .map((t) => t.value)
                                        .join(', ')
                                }}
                            </p>
                            <p>
                                <span class="font-medium text-foreground"
                                    >Condition operators:</span
                                >
                                {{ options.conditionOperators.join(', ') }}
                            </p>
                            <p>
                                <span class="font-medium text-foreground"
                                    >Calculation operators:</span
                                >
                                {{ options.calculationOperators.join(', ') }}
                            </p>
                            <p>
                                <span class="font-medium text-foreground"
                                    >Patient context:</span
                                >
                                {{ options.contextKeys.join(', ') }}
                            </p>
                        </div>
                        <p class="mt-2">
                            Every field binds to a concept by its local code
                            (<code>"concept": "OBS-WEIGHT"</code>). Conditions
                            look like
                            <code
                                >{"field": "pregnant", "op": "eq", "value":
                                true}</code
                            >, grouped with <code>all</code>,
                            <code>any</code> and <code>not</code>. A section
                            with a <code>"repeat"</code> object repeats. Labels
                            may be a string or a map of locale to text.
                        </p>
                    </details>
                </section>
            </div>

            <!-- Right: live preview and versions -->
            <div class="flex flex-col gap-4">
                <section class="rounded-xl border border-border bg-card p-5">
                    <div
                        class="mb-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2
                            class="flex items-center gap-2 text-base font-semibold"
                        >
                            <Eye class="size-4 text-muted-foreground" />
                            Preview
                        </h2>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground"
                                >Sample patient: {{ sampleContext.sex }},
                                {{ sampleContext.age }}y</span
                            >
                            <div
                                class="inline-flex rounded-md border border-border p-0.5"
                            >
                                <button
                                    v-for="m in options.entryModes"
                                    :key="m.value"
                                    type="button"
                                    class="rounded px-2.5 py-1 text-xs"
                                    :class="
                                        previewMode === m.value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="
                                        previewMode = m.value as FormEntryMode
                                    "
                                >
                                    {{
                                        m.value === 'guided'
                                            ? 'Guided'
                                            : 'Rapid'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="parseError" class="text-sm text-muted-foreground">
                        Fix the JSON to see the preview.
                    </p>
                    <FormPreview
                        v-else
                        :key="previewKey"
                        :definition="parsed"
                        :context="sampleContext"
                        :mode="previewMode"
                    />
                </section>

                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        Published versions
                    </h2>
                    <ul
                        v-if="versions.length"
                        class="divide-y divide-border/60 text-sm"
                    >
                        <li
                            v-for="v in versions"
                            :key="v.version"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div>
                                <span class="font-mono text-xs"
                                    >v{{ v.version }}</span
                                >
                                <span class="ml-2">{{
                                    v.published_at_label
                                }}</span>
                                <span
                                    v-if="v.published_by"
                                    class="ml-1 text-muted-foreground"
                                    >by {{ v.published_by }}</span
                                >
                                <p
                                    v-if="v.notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ v.notes }}
                                </p>
                            </div>
                            <span class="text-xs text-muted-foreground"
                                >{{ v.submissions_count }} filled</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        Not published yet.
                    </p>
                </section>
            </div>
        </div>

        <Dialog
            :open="publishOpen"
            @update:open="(v: boolean) => (publishOpen = v)"
        >
            <DialogContent>
                <form class="flex flex-col gap-4" @submit.prevent="doPublish">
                    <DialogHeader>
                        <DialogTitle
                            >Publish version
                            {{ (form.published_version ?? 0) + 1 }}</DialogTitle
                        >
                        <DialogDescription>
                            The saved draft is frozen as a new version and
                            offered to clinicians. Forms already filled keep the
                            version they were filled with.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex flex-col gap-1.5">
                        <Label for="publish-notes">What changed</Label>
                        <Input
                            id="publish-notes"
                            v-model="publish.notes"
                            placeholder="Optional"
                        />
                        <InputError :message="publish.errors.notes" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="publishOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="publish.processing"
                            >Publish</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
