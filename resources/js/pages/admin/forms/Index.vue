<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardList, Plus, Upload } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
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

const storeUrl = '/admin/forms';
const importUrl = '/admin/forms/import';

type CatalogueForm = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    status: 'draft' | 'published' | 'retired';
    status_label: string;
    entry_mode_label: string;
    encounter_types: string[];
    roles: string[];
    published_version: number | null;
    has_unpublished_changes: boolean;
    submissions_count: number;
    updated_at_label: string | null;
    url: string;
};

defineProps<{
    forms: CatalogueForm[];
    options: {
        encounterTypes: Option[];
        roles: Option[];
        entryModes: Option[];
    };
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin/users' },
            { title: 'Clinical forms', href: '/admin/forms' },
        ],
    },
});

const createOpen = ref(false);
const importOpen = ref(false);

const create = useForm({
    name: '',
    description: '',
    encounter_types: [] as string[],
});

function toggleType(value: string) {
    const i = create.encounter_types.indexOf(value);

    if (i === -1) {
        create.encounter_types.push(value);
    } else {
        create.encounter_types.splice(i, 1);
    }
}

function submitCreate() {
    create.post(storeUrl, {
        onSuccess: () => {
            createOpen.value = false;
            create.reset();
        },
    });
}

const importing = useForm({ json: '', file: null as File | null });

function pickFile(event: Event) {
    importing.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submitImport() {
    importing.post(importUrl, {
        forceFormData: true,
        onSuccess: () => {
            importOpen.value = false;
            importing.reset();
        },
    });
}

const statusVariant = (status: string) =>
    status === 'published'
        ? 'default'
        : status === 'retired'
          ? 'destructive'
          : 'secondary';
</script>

<template>
    <Head title="Clinical forms" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <AdminNav current="forms" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                >
                    <ClipboardList class="size-5 text-muted-foreground" />
                    Clinical forms
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Data-capture templates defined as versioned JSON. Publish a
                    form to offer it on the encounter types it is assigned to.
                </p>
            </div>
            <div v-if="canManage" class="flex gap-2">
                <Button variant="outline" @click="importOpen = true">
                    <Upload class="size-4" />
                    Import
                </Button>
                <Button @click="createOpen = true">
                    <Plus class="size-4" />
                    New form
                </Button>
            </div>
        </div>

        <section
            class="overflow-x-auto rounded-xl border border-border bg-card"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2 font-medium">Form</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Offered on</th>
                        <th class="px-4 py-2 font-medium">Roles</th>
                        <th class="px-4 py-2 font-medium">Entry</th>
                        <th class="px-4 py-2 text-right font-medium">Filled</th>
                        <th class="px-4 py-2 font-medium">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <tr v-for="f in forms" :key="f.id">
                        <td class="px-4 py-2">
                            <Link
                                :href="f.url"
                                class="font-medium hover:underline"
                                >{{ f.name }}</Link
                            >
                            <span
                                class="ml-2 font-mono text-[11px] text-muted-foreground"
                                >{{ f.slug }}</span
                            >
                            <p
                                v-if="f.description"
                                class="line-clamp-1 text-xs text-muted-foreground"
                            >
                                {{ f.description }}
                            </p>
                        </td>
                        <td class="px-4 py-2">
                            <Badge :variant="statusVariant(f.status)">{{
                                f.status_label
                            }}</Badge>
                            <span
                                v-if="f.published_version"
                                class="ml-1 font-mono text-[11px] text-muted-foreground"
                                >v{{ f.published_version }}</span
                            >
                            <span
                                v-if="
                                    f.has_unpublished_changes &&
                                    f.published_version
                                "
                                class="ml-1 text-[11px] text-amber-700 dark:text-amber-300"
                                >draft changes</span
                            >
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ f.encounter_types.join(', ') || '—' }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                f.roles.length
                                    ? f.roles.join(', ')
                                    : 'Anyone documenting'
                            }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ f.entry_mode_label.split(' ')[0] }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ f.submissions_count }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ f.updated_at_label }}
                        </td>
                    </tr>
                    <tr v-if="!forms.length">
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No forms yet. Create one or import a form file.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Dialog
            :open="createOpen"
            @update:open="(v: boolean) => (createOpen = v)"
        >
            <DialogContent>
                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submitCreate"
                >
                    <DialogHeader>
                        <DialogTitle>New form</DialogTitle>
                        <DialogDescription
                            >Starts as an empty draft you design on the next
                            screen.</DialogDescription
                        >
                    </DialogHeader>
                    <div class="flex flex-col gap-1.5">
                        <Label for="new-name">Name</Label>
                        <Input
                            id="new-name"
                            v-model="create.name"
                            required
                            placeholder="e.g. Paediatric assessment"
                        />
                        <InputError :message="create.errors.name" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="new-description">Description</Label>
                        <Input
                            id="new-description"
                            v-model="create.description"
                            placeholder="What the form captures"
                        />
                        <InputError :message="create.errors.description" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Offered on</Label>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <label
                                v-for="t in options.encounterTypes"
                                :key="t.value"
                                class="inline-flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="
                                        create.encounter_types.includes(t.value)
                                    "
                                    @update:model-value="toggleType(t.value)"
                                />
                                {{ t.label }}
                            </label>
                        </div>
                        <InputError :message="create.errors.encounter_types" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="create.processing"
                            >Create draft</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="importOpen"
            @update:open="(v: boolean) => (importOpen = v)"
        >
            <DialogContent>
                <form
                    class="flex flex-col gap-4"
                    @submit.prevent="submitImport"
                >
                    <DialogHeader>
                        <DialogTitle>Import a form file</DialogTitle>
                        <DialogDescription>
                            A file exported from this or another installation.
                            It is imported as a draft; a form with the same slug
                            has its draft replaced.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex flex-col gap-1.5">
                        <Label for="import-file">File</Label>
                        <input
                            id="import-file"
                            type="file"
                            accept=".json,application/json"
                            class="text-sm"
                            @change="pickFile"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="import-json">…or paste the JSON</Label>
                        <textarea
                            id="import-json"
                            v-model="importing.json"
                            rows="6"
                            :class="[
                                textareaClass,
                                'resize-y overflow-auto font-mono text-xs',
                            ]"
                        />
                        <InputError
                            :message="
                                importing.errors.file ?? importing.errors.json
                            "
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="importOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="
                                importing.processing ||
                                (!importing.file && !importing.json.trim())
                            "
                            >Import</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
