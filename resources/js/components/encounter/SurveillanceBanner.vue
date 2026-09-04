<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Radar } from '@lucide/vue';

export type SurveillanceCaseFlag = {
    id: number;
    disease: string;
    category: 'immediate' | 'weekly';
    category_label: string;
    instruction: string;
    classification: string;
    classification_label: string;
    notification_status: string;
    notification_label: string;
    detected_at: string;
    href: string;
};

defineProps<{
    cases: SurveillanceCaseFlag[];
}>();
</script>

<template>
    <div v-if="cases.length" class="flex flex-col gap-2">
        <div
            v-for="c in cases"
            :key="c.id"
            class="flex flex-wrap items-center gap-3 rounded-lg border px-4 py-2.5 text-sm"
            :class="
                c.notification_status === 'pending'
                    ? 'border-red-500/40 bg-red-500/5 text-red-900 dark:text-red-200'
                    : c.category === 'immediate'
                      ? 'border-emerald-500/30 bg-emerald-500/5'
                      : 'border-amber-500/30 bg-amber-500/5 text-amber-900 dark:text-amber-200'
            "
        >
            <component
                :is="c.category === 'immediate' ? AlertTriangle : Radar"
                class="size-4 shrink-0"
            />
            <div class="min-w-0 flex-1">
                <p class="font-medium">
                    {{ c.disease }} · {{ c.category_label }} ·
                    {{ c.classification_label }}
                </p>
                <p class="text-xs opacity-80">
                    <template v-if="c.notification_status === 'pending'">
                        {{ c.instruction }}
                    </template>
                    <template v-else>
                        {{ c.notification_label }} · detected
                        {{ c.detected_at }}
                    </template>
                </p>
            </div>
            <Link
                :href="c.href"
                class="inline-flex shrink-0 items-center gap-1 text-xs font-medium underline-offset-2 hover:underline"
            >
                {{
                    c.notification_status === 'pending'
                        ? 'Record notification'
                        : 'Open case'
                }}
                <ArrowRight class="size-3.5" />
            </Link>
        </div>
    </div>
</template>
