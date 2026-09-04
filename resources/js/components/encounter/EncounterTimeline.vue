<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, HeartPulse, Stethoscope } from '@lucide/vue';
import { ref } from 'vue';
import type { EncounterSummary } from '@/types/clinical';

/**
 * Every clinical contact on one list — consultations, nursing sessions, ward
 * rounds — each expandable to its full note.
 */
withDefaults(
    defineProps<{
        encounters: EncounterSummary[];
        emptyText?: string;
        /** Link each entry through to its encounter screen. */
        linked?: boolean;
    }>(),
    {
        emptyText: 'No clinical encounters yet.',
        linked: true,
    },
);

const expanded = ref<Set<number>>(new Set());

function toggle(id: number) {
    const next = new Set(expanded.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expanded.value = next;
}

const typeClass = (type: string) =>
    type === 'consultation'
        ? 'bg-primary/10 text-primary'
        : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';

const sections = (e: EncounterSummary) =>
    [
        { label: 'Presenting complaint', value: e.presenting_complaint },
        {
            label: e.type === 'consultation' ? 'History' : 'Patient report',
            value: e.subjective,
        },
        {
            label: e.type === 'consultation' ? 'Examination' : 'Observations',
            value: e.objective,
        },
        {
            label:
                e.type === 'consultation' ? 'Impression' : 'Nursing assessment',
            value: e.assessment,
        },
        {
            label: e.type === 'consultation' ? 'Plan' : 'Intervention',
            value: e.plan,
        },
    ].filter((s) => s.value);
</script>

<template>
    <p v-if="!encounters.length" class="text-sm text-muted-foreground">
        {{ emptyText }}
    </p>
    <ol v-else class="flex flex-col divide-y divide-border">
        <li
            v-for="e in encounters"
            :key="e.id"
            class="py-3 first:pt-0 last:pb-0"
        >
            <button
                type="button"
                class="flex w-full items-start gap-3 text-left"
                :aria-expanded="expanded.has(e.id)"
                @click="toggle(e.id)"
            >
                <span
                    class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full"
                    :class="typeClass(e.type)"
                >
                    <Stethoscope
                        v-if="e.type === 'consultation'"
                        class="size-3.5"
                    />
                    <HeartPulse v-else class="size-3.5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span
                        class="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5"
                    >
                        <span class="text-sm font-medium">
                            {{
                                e.diagnoses.length
                                    ? e.diagnoses.join('; ')
                                    : e.assessment ||
                                      e.service_point ||
                                      e.type_label
                            }}
                        </span>
                        <span
                            class="shrink-0 text-xs text-muted-foreground/70"
                            >{{ e.date }}</span
                        >
                    </span>
                    <span
                        class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                    >
                        <span>{{ e.type_label }}</span>
                        <span v-if="e.service_point"
                            >· {{ e.service_point }}</span
                        >
                        <span v-if="e.author">· {{ e.author }}</span>
                        <span
                            v-if="e.status !== 'signed'"
                            class="rounded bg-amber-500/10 px-1.5 text-[11px] font-medium text-amber-700 dark:text-amber-400"
                            >{{ e.status_label }}</span
                        >
                    </span>
                </span>
                <ChevronDown
                    class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="{ 'rotate-180': expanded.has(e.id) }"
                />
            </button>
            <div
                v-if="expanded.has(e.id)"
                class="mt-3 ml-10 flex flex-col gap-3"
            >
                <dl v-if="sections(e).length" class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="s in sections(e)"
                        :key="s.label"
                        :class="
                            s.label === 'Plan' || s.label === 'Intervention'
                                ? 'sm:col-span-2'
                                : ''
                        "
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ s.label }}
                        </dt>
                        <dd class="text-sm whitespace-pre-line">
                            {{ s.value }}
                        </dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-muted-foreground">
                    No narrative recorded.
                </p>
                <ol
                    v-if="e.addenda?.length"
                    class="flex flex-col gap-2 border-l-2 border-primary/30 pl-3"
                >
                    <li v-for="a in e.addenda" :key="a.id">
                        <p class="text-xs font-semibold text-muted-foreground">
                            Addendum · {{ a.author ?? 'Unknown author' }} ·
                            {{ a.recorded_at_label }}
                        </p>
                        <p class="text-sm whitespace-pre-line">{{ a.body }}</p>
                    </li>
                </ol>
                <div
                    class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                >
                    <span v-if="e.outcome"
                        >Outcome:
                        <span class="text-foreground">{{
                            e.outcome
                        }}</span></span
                    >
                    <Link
                        v-if="linked"
                        :href="e.url"
                        class="font-medium text-primary hover:underline"
                        >Open encounter</Link
                    >
                </div>
            </div>
        </li>
    </ol>
</template>
