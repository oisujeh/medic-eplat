<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    Clock,
    ExternalLink,
    MapPin,
    Pencil,
    Phone,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useElapsed } from '@/composables/useElapsed';
import type { Allergy, Encounter, PatientBanner } from '@/types/clinical';

const props = defineProps<{
    patient: PatientBanner;
    encounter: Encounter;
    allergies: Allergy[];
}>();

const emit = defineEmits<{ 'manage-allergies': [] }>();

const { startedLabel, durationLabel } = useElapsed(props.encounter.started_at);
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card">
        <div class="flex flex-wrap items-start justify-between gap-4 p-5">
            <div class="flex items-start gap-4">
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary"
                >
                    {{ patient.initials }}
                </span>
                <div class="flex flex-col gap-1.5">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h1 class="text-lg font-semibold tracking-tight">
                            {{ patient.name }}
                        </h1>
                        <span
                            class="inline-flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <span>{{ patient.sex_label }}</span>
                            <span v-if="patient.age !== null" aria-hidden
                                >·</span
                            >
                            <span v-if="patient.age !== null"
                                >{{ patient.age }} yrs</span
                            >
                        </span>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            >Hospital No:
                            <span
                                class="font-mono font-medium text-foreground"
                                >{{ patient.file_number }}</span
                            ></span
                        >
                        <span
                            v-if="patient.dob"
                            class="inline-flex items-center gap-1"
                            ><CalendarDays class="size-3.5" />DOB:
                            <span class="text-foreground">{{
                                patient.dob
                            }}</span></span
                        >
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="patient.phone"
                            class="inline-flex items-center gap-1"
                            ><Phone class="size-3.5" />{{ patient.phone }}</span
                        >
                        <span
                            v-if="patient.address"
                            class="inline-flex items-center gap-1"
                            ><MapPin class="size-3.5" />{{
                                patient.address
                            }}</span
                        >
                        <button
                            type="button"
                            class="group inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium transition-colors"
                            :class="
                                allergies.length
                                    ? 'bg-red-500/10 text-red-700 ring-1 ring-red-500/30 hover:bg-red-500/20 dark:text-red-400'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="emit('manage-allergies')"
                        >
                            <AlertTriangle class="size-3.5" />Allergies:
                            {{
                                allergies.length
                                    ? allergies
                                          .map((a) => a.substance)
                                          .join(', ')
                                    : 'None recorded'
                            }}
                            <Pencil
                                class="size-3 opacity-0 transition-opacity group-hover:opacity-70"
                            />
                        </button>
                    </div>
                </div>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="patient.url">
                    <ExternalLink class="size-4" />
                    Full profile
                </Link>
            </Button>
        </div>
        <div
            class="flex flex-wrap items-center gap-x-8 gap-y-1.5 border-t border-border bg-muted/40 px-5 py-2.5 text-xs"
        >
            <span
                >{{ encounter.type_label }}:
                <span class="font-medium text-foreground">{{
                    encounter.service_point ?? '—'
                }}</span></span
            >
            <span
                >Visit:
                <span class="font-medium text-foreground"
                    >{{ encounter.visit_date
                    }}<span v-if="encounter.visit_number" class="font-mono">
                        · {{ encounter.visit_number }}</span
                    ></span
                ></span
            >
            <span
                >Author:
                <span class="font-medium text-foreground">{{
                    encounter.author ?? '—'
                }}</span></span
            >
            <span
                v-if="!encounter.is_open"
                class="ml-auto inline-flex items-center gap-1.5 rounded-md bg-emerald-500/10 px-2 py-1 font-medium text-emerald-700 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-3.5" />
                {{ encounter.status_label }}
                <span
                    v-if="encounter.signed_at_label"
                    class="font-normal opacity-80"
                    >· {{ encounter.signed_at_label }}</span
                >
            </span>
            <span
                v-else-if="startedLabel"
                class="ml-auto inline-flex items-center gap-1.5 rounded-md bg-background px-2 py-1 font-medium text-foreground ring-1 ring-border"
                :title="`Started ${startedLabel}`"
            >
                <Clock class="size-3.5 text-primary" />
                {{ durationLabel }}
                <span class="font-normal text-muted-foreground"
                    >· started {{ startedLabel }}</span
                >
            </span>
        </div>
    </div>
</template>
