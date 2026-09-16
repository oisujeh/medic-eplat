<script setup lang="ts">
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import statesData from '@/data/state_lga.json';

export type FacilityProfileForm = {
    name: string;
    code: string;
    state: string;
    lga: string;
};

type StateEntry = { state: string; alias: string; lgas: string[] };

const props = defineProps<{
    errors: Partial<Record<keyof FacilityProfileForm, string>>;
    /** Show only one group of fields; omit to show them all. */
    section?: 'identity' | 'location';
}>();

const name = defineModel<string>('name', { required: true });
const code = defineModel<string>('code', { required: true });
const state = defineModel<string>('state', { required: true });
const lga = defineModel<string>('lga', { required: true });

const showIdentity = computed(() => props.section !== 'location');
const showLocation = computed(() => props.section !== 'identity');

const states = (statesData as StateEntry[]).map((entry) => entry.state).sort();

const lgasForState = computed<string[]>(
    () =>
        (statesData as StateEntry[]).find(
            (entry) => entry.state === state.value,
        )?.lgas ?? [],
);

// An LGA belongs to one state, so changing the state clears a stale choice.
watch(state, () => {
    if (!lgasForState.value.includes(lga.value)) {
        lga.value = '';
    }
});
</script>

<template>
    <div class="grid gap-5">
        <template v-if="showIdentity">
            <div class="grid gap-1.5">
                <Label for="facility-name">Facility name *</Label>
                <Input
                    id="facility-name"
                    v-model="name"
                    name="name"
                    autocomplete="organization"
                    maxlength="150"
                    placeholder="e.g. Ikeja General Hospital"
                />
                <p class="text-xs text-muted-foreground">
                    The official name, as it should appear on reports and
                    printed documents.
                </p>
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="facility-code">Facility code *</Label>
                <Input
                    id="facility-code"
                    v-model="code"
                    name="code"
                    class="font-mono"
                    autocomplete="off"
                    maxlength="50"
                    placeholder="e.g. 25/08/1/1/1/0001"
                />
                <p class="text-xs text-muted-foreground">
                    The facility's registry code, such as its Health Facility
                    Registry or NHMIS code. Letters, numbers, slashes, dashes
                    and dots only.
                </p>
                <InputError :message="errors.code" />
            </div>
        </template>

        <template v-if="showLocation">
            <div class="grid gap-1.5">
                <Label for="facility-state">State *</Label>
                <Select v-model="state">
                    <SelectTrigger id="facility-state" class="w-full">
                        <SelectValue placeholder="Select state" />
                    </SelectTrigger>
                    <SelectContent class="max-h-72">
                        <SelectItem v-for="s in states" :key="s" :value="s">{{
                            s
                        }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.state" />
            </div>

            <div class="grid gap-1.5">
                <Label for="facility-lga">Local government area *</Label>
                <Select v-model="lga" :disabled="!state">
                    <SelectTrigger id="facility-lga" class="w-full">
                        <SelectValue
                            :placeholder="
                                state ? 'Select LGA' : 'Select a state first'
                            "
                        />
                    </SelectTrigger>
                    <SelectContent class="max-h-72">
                        <SelectItem
                            v-for="l in lgasForState"
                            :key="l"
                            :value="l"
                            >{{ l }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="errors.lga" />
            </div>
        </template>
    </div>
</template>
