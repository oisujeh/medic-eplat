<script setup lang="ts">
import { CheckCircle2, Save } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { useEncounterContext } from '@/composables/useEncounterForm';
import type { Option, ServicePointOption } from '@/types/clinical';

/**
 * Where the patient goes next, and the two ways out of an encounter: save a
 * draft or sign it off.
 */
const props = defineProps<{
    onwardServicePoints: ServicePointOption[];
    priorities: Option[];
    canSign: boolean;
}>();

const { encounter, form, readOnly, saveDraft, sign } = useEncounterContext();

const personnel = computed(
    () =>
        props.onwardServicePoints.find(
            (sp) => String(sp.id) === form.next_service_point_id,
        )?.personnel ?? [],
);

watch(
    () => form.next_service_point_id,
    () => {
        form.next_assigned_to = 'none';
    },
);

const signLabel = computed(() => `Sign ${encounter.type_label.toLowerCase()}`);
</script>

<template>
    <div
        v-if="!readOnly"
        class="flex flex-col gap-4 rounded-xl border border-border bg-card p-5"
    >
        <div>
            <h3 class="text-sm font-semibold">Next department</h3>
            <p class="text-xs text-muted-foreground">
                Route the patient onward when you sign, or complete the visit
                here.
            </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="grid gap-1.5 sm:col-span-2">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                        :class="
                            form.next_service_point_id === 'none'
                                ? 'border-primary bg-primary/10 font-medium text-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'
                        "
                        @click="form.next_service_point_id = 'none'"
                    >
                        Complete only
                    </button>
                    <button
                        v-for="sp in onwardServicePoints"
                        :key="sp.id"
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                        :class="
                            form.next_service_point_id === String(sp.id)
                                ? 'border-primary bg-primary/10 font-medium text-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'
                        "
                        @click="form.next_service_point_id = String(sp.id)"
                    >
                        {{ sp.name }}
                    </button>
                </div>
            </div>
            <div
                v-if="form.next_service_point_id !== 'none'"
                class="grid gap-1.5"
            >
                <Label>Priority</Label>
                <Select v-model="form.next_priority">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="p in priorities"
                            :key="p.value"
                            :value="p.value"
                            >{{ p.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div
                v-if="form.next_service_point_id !== 'none'"
                class="grid gap-1.5"
            >
                <Label>Assign to personnel</Label>
                <Select v-model="form.next_assigned_to">
                    <SelectTrigger class="w-full">
                        <SelectValue
                            placeholder="Unassigned — anyone at this point"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none"
                            >Unassigned — anyone at this point</SelectItem
                        >
                        <SelectItem
                            v-for="person in personnel"
                            :key="person.id"
                            :value="String(person.id)"
                            >{{ person.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div
                v-if="form.next_service_point_id !== 'none'"
                class="grid gap-1.5 sm:col-span-2"
            >
                <Label>Note (optional)</Label>
                <Input
                    v-model="form.next_note"
                    placeholder="Reason for onward routing"
                />
            </div>
        </div>

        <div
            class="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-4"
        >
            <Button
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="saveDraft"
            >
                <Save class="size-4" />
                Save draft
            </Button>
            <Button
                type="button"
                :disabled="form.processing || !canSign"
                @click="sign"
            >
                <CheckCircle2 class="size-4" />
                {{ signLabel }}
            </Button>
        </div>
    </div>
</template>
