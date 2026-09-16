<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Link2, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type Relationship = {
    id: number;
    type: string;
    type_label: string;
    notes: string | null;
    name: string;
    file_number: string;
    sex: string;
    age: number | null;
    url: string;
};

type Candidate = {
    id: number;
    name: string;
    file_number: string;
    sex: string;
    age: number | null;
};

/**
 * Who this patient is linked to on the register: mother and child,
 * household members, an index case and its contacts.
 */
const props = defineProps<{
    patientId: number;
    relationships: Relationship[];
    types: Array<{ value: string; label: string }>;
    canEdit: boolean;
}>();

const linking = ref(false);
const query = ref('');
const results = ref<Candidate[]>([]);
const chosen = ref<Candidate | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

const form = useForm({
    related_patient_id: '' as string | number,
    type: '',
    notes: '',
});

watch(query, (q) => {
    clearTimeout(timer);

    if (q.trim().length < 2) {
        results.value = [];

        return;
    }

    timer = setTimeout(async () => {
        const res = await fetch(
            `/patients/${props.patientId}/relationships/search?q=${encodeURIComponent(q)}`,
            { headers: { Accept: 'application/json' } },
        );
        results.value = (await res.json()).patients ?? [];
    }, 250);
});

function choose(candidate: Candidate) {
    chosen.value = candidate;
    form.related_patient_id = candidate.id;
    results.value = [];
    query.value = '';
}

function submit() {
    form.post(`/patients/${props.patientId}/relationships`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            chosen.value = null;
            linking.value = false;
        },
    });
}

function remove(link: Relationship) {
    router.delete(`/patients/${props.patientId}/relationships/${link.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <Link2 class="size-4 text-muted-foreground" />
                Linked patients
            </h2>
            <Button
                v-if="canEdit"
                type="button"
                variant="outline"
                size="sm"
                @click="linking = !linking"
            >
                <Plus class="size-4" />
                Link a patient
            </Button>
        </div>

        <ul v-if="relationships.length" class="divide-y divide-border/60">
            <li
                v-for="link in relationships"
                :key="link.id"
                class="flex items-center justify-between gap-3 py-2 text-sm"
            >
                <div class="min-w-0">
                    <span class="text-muted-foreground"
                        >{{ link.type_label }}:</span
                    >
                    <Link
                        :href="link.url"
                        class="ml-1 font-medium hover:underline"
                        >{{ link.name }}</Link
                    >
                    <span
                        class="ml-1 font-mono text-xs text-muted-foreground"
                        >{{ link.file_number }}</span
                    >
                    <span
                        v-if="link.age !== null"
                        class="ml-1 text-xs text-muted-foreground"
                        >· {{ link.sex }}, {{ link.age }}y</span
                    >
                    <p v-if="link.notes" class="text-xs text-muted-foreground">
                        {{ link.notes }}
                    </p>
                </div>
                <Button
                    v-if="canEdit"
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Remove link"
                    @click="remove(link)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            No linked patients yet.
        </p>

        <form
            v-if="linking && canEdit"
            class="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="relative grid gap-1.5 sm:col-span-2">
                <Label for="relationship-search">Patient</Label>
                <div
                    v-if="chosen"
                    class="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
                >
                    <span
                        >{{ chosen.name }}
                        <span class="font-mono text-xs text-muted-foreground">{{
                            chosen.file_number
                        }}</span></span
                    >
                    <button
                        type="button"
                        class="text-xs text-muted-foreground hover:underline"
                        @click="
                            chosen = null;
                            form.related_patient_id = '';
                        "
                    >
                        Change
                    </button>
                </div>
                <Input
                    v-else
                    id="relationship-search"
                    v-model="query"
                    autocomplete="off"
                    placeholder="Search by name or file number"
                />
                <ul
                    v-if="results.length"
                    class="absolute top-full z-10 mt-1 w-full rounded-md border border-border bg-popover shadow-md"
                >
                    <li v-for="c in results" :key="c.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent"
                            @click="choose(c)"
                        >
                            <span>{{ c.name }}</span>
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ c.file_number }}</span
                            >
                        </button>
                    </li>
                </ul>
                <InputError :message="form.errors.related_patient_id" />
            </div>
            <div class="grid gap-1.5">
                <Label for="relationship-type">Is this patient's</Label>
                <Select v-model="form.type">
                    <SelectTrigger id="relationship-type" class="w-full">
                        <SelectValue placeholder="Select relationship" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="t in types"
                            :key="t.value"
                            :value="t.value"
                            >{{ t.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.type" />
            </div>
            <div class="grid gap-1.5">
                <Label for="relationship-notes">Note (optional)</Label>
                <Input id="relationship-notes" v-model="form.notes" />
            </div>
            <div class="flex items-center gap-2 sm:col-span-2">
                <Button
                    type="submit"
                    size="sm"
                    :disabled="
                        form.processing ||
                        !form.related_patient_id ||
                        !form.type
                    "
                >
                    Save link
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="linking = false"
                >
                    Cancel
                </Button>
            </div>
        </form>
    </section>
</template>
