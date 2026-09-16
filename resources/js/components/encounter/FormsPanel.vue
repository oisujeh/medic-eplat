<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList, Play } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AvailableForm, FormSubmissionSummary } from '@/types/forms';

/**
 * The clinical forms on an encounter: those already started or completed,
 * and those the encounter type offers that have not been started.
 */
defineProps<{
    submissions: FormSubmissionSummary[];
    available: AvailableForm[];
}>();

const badgeVariant = (status: string) =>
    status === 'completed'
        ? 'default'
        : status === 'voided'
          ? 'destructive'
          : 'secondary';
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
            <ClipboardList class="size-4 text-muted-foreground" />
            Clinical forms
        </h2>

        <ul v-if="submissions.length" class="mb-3 divide-y divide-border/60">
            <li
                v-for="s in submissions"
                :key="s.id"
                class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
            >
                <div class="min-w-0">
                    <Link :href="s.url" class="font-medium hover:underline">
                        {{ s.form_name }}
                    </Link>
                    <span
                        class="ml-2 font-mono text-[11px] text-muted-foreground"
                        >v{{ s.version }}</span
                    >
                    <p class="text-xs text-muted-foreground">
                        <span v-if="s.by">{{ s.by }} · </span>{{ s.at_label }}
                        <span v-if="s.void_reason"> · {{ s.void_reason }}</span>
                    </p>
                </div>
                <Badge :variant="badgeVariant(s.status)">{{
                    s.status_label
                }}</Badge>
            </li>
        </ul>

        <div v-if="available.length" class="flex flex-wrap gap-2">
            <Button
                v-for="f in available"
                :key="f.id"
                as-child
                variant="outline"
                size="sm"
            >
                <Link :href="f.url" :title="f.description ?? undefined">
                    <Play class="size-3.5" />
                    {{ f.name }}
                </Link>
            </Button>
        </div>

        <p
            v-if="!submissions.length && !available.length"
            class="text-sm text-muted-foreground"
        >
            No forms are assigned to this encounter type.
        </p>
    </section>
</template>
