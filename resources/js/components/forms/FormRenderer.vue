<script setup lang="ts">
import { ChevronLeft, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FormFieldInput from '@/components/forms/FormFieldInput.vue';
import { Button } from '@/components/ui/button';
import { useFormFill } from '@/composables/useFormFill';
import { emptyInstance, isRepeating, labelOf } from '@/lib/formEngine';
import type { FormEntryMode, FormInstance, FormSection } from '@/types/forms';

/**
 * Renders a form definition against the answers provided by
 * provideFormFill(): guided mode shows one page at a time; rapid mode lays
 * every page out on one screen, compact and keyboard-first (PRD FRM-07).
 */
const props = defineProps<{ mode: FormEntryMode }>();

const fill = useFormFill();
const { definition, evaluator, data, locale } = fill;

// Only pages the skip logic shows are stepped through.
const visiblePages = computed(() =>
    (definition.pages ?? []).filter((p) => evaluator.isVisible(p, null)),
);

const pageIndex = ref(0);

watch(visiblePages, (pages) => {
    if (pageIndex.value >= pages.length) {
        pageIndex.value = Math.max(0, pages.length - 1);
    }
});

const currentPages = computed(() =>
    props.mode === 'guided'
        ? visiblePages.value.slice(pageIndex.value, pageIndex.value + 1)
        : visiblePages.value,
);

const readonly = computed(() => fill.readonly.value);

function instances(section: FormSection): FormInstance[] {
    const list = data[section.key];

    return Array.isArray(list) ? (list as FormInstance[]) : [];
}

function addInstance(section: FormSection) {
    if (readonly.value) {
        return;
    }

    const list = instances(section);
    const max = section.repeat?.max;

    if (max !== undefined && max !== null && list.length >= max) {
        return;
    }

    if (!Array.isArray(data[section.key])) {
        data[section.key] = [];
    }

    (data[section.key] as FormInstance[]).push(emptyInstance(section));
}

function removeInstance(section: FormSection, index: number) {
    if (readonly.value) {
        return;
    }

    instances(section).splice(index, 1);
}

function canAdd(section: FormSection): boolean {
    const max = section.repeat?.max;

    return max === undefined || max === null || instances(section).length < max;
}

/** The first page carrying an error, so "Complete" can jump to it. */
function goToFirstError(paths: string[]): void {
    if (props.mode !== 'guided' || !paths.length) {
        return;
    }

    const firstKey = paths[0].split('.')[0];

    const index = visiblePages.value.findIndex((page) =>
        page.sections.some(
            (section) =>
                section.key === firstKey ||
                section.fields.some((f) => f.key === firstKey),
        ),
    );

    if (index >= 0) {
        pageIndex.value = index;
    }
}

defineExpose({ goToFirstError, pageIndex });
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Guided: the page stepper -->
        <ol
            v-if="mode === 'guided' && visiblePages.length > 1"
            class="flex flex-wrap gap-1"
            aria-label="Pages"
        >
            <li v-for="(page, i) in visiblePages" :key="page.key">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs transition-colors"
                    :class="
                        i === pageIndex
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border text-muted-foreground hover:text-foreground'
                    "
                    :aria-current="i === pageIndex ? 'step' : undefined"
                    @click="pageIndex = i"
                >
                    {{ i + 1 }}. {{ labelOf(page, locale, 'title') }}
                </button>
            </li>
        </ol>

        <section
            v-for="page in currentPages"
            :key="page.key"
            class="flex flex-col gap-4"
            :data-page="page.key"
        >
            <header v-if="mode === 'rapid' || visiblePages.length > 1">
                <h2 class="text-base font-semibold">
                    {{ labelOf(page, locale, 'title') }}
                </h2>
                <p
                    v-if="page.description"
                    class="text-sm text-muted-foreground"
                >
                    {{ labelOf(page, locale, 'description') }}
                </p>
            </header>

            <template v-for="section in page.sections" :key="section.key">
                <fieldset
                    v-if="evaluator.isVisible(section, null)"
                    class="rounded-xl border border-border bg-card p-4"
                    :data-section="section.key"
                >
                    <legend
                        v-if="section.title"
                        class="px-1 text-sm font-semibold"
                    >
                        {{ labelOf(section, locale, 'title') }}
                    </legend>
                    <p
                        v-if="section.description"
                        class="mb-3 text-sm text-muted-foreground"
                    >
                        {{ labelOf(section, locale, 'description') }}
                    </p>

                    <!-- Repeating group: one card per repeat, fully isolated -->
                    <template v-if="isRepeating(section)">
                        <p
                            v-if="fill.errorFor(section.key)"
                            class="mb-2 text-xs text-destructive"
                            role="alert"
                        >
                            {{ fill.errorFor(section.key) }}
                        </p>
                        <ol class="flex flex-col gap-3">
                            <li
                                v-for="(instance, i) in instances(section)"
                                :key="i"
                                class="rounded-lg border border-border/70 bg-background p-3"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{ labelOf(section, locale, 'title') }}
                                        {{ i + 1 }}
                                    </span>
                                    <Button
                                        v-if="!readonly"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-muted-foreground hover:text-destructive"
                                        :aria-label="`Remove entry ${i + 1}`"
                                        @click="removeInstance(section, i)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                                <div
                                    :class="
                                        mode === 'rapid'
                                            ? 'grid gap-2'
                                            : 'grid gap-4 sm:grid-cols-2'
                                    "
                                >
                                    <template
                                        v-for="field in section.fields"
                                        :key="field.key"
                                    >
                                        <FormFieldInput
                                            v-if="
                                                evaluator.isVisible(
                                                    field,
                                                    instance,
                                                )
                                            "
                                            :field="field"
                                            :scope="instance"
                                            :path="`${section.key}.${i}.${field.key}`"
                                            :compact="mode === 'rapid'"
                                        />
                                    </template>
                                </div>
                            </li>
                        </ol>
                        <Button
                            v-if="!readonly"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="mt-3"
                            :disabled="!canAdd(section)"
                            @click="addInstance(section)"
                        >
                            <Plus class="size-4" />
                            {{ section.repeat?.add_label ?? 'Add' }}
                        </Button>
                        <p
                            v-else-if="instances(section).length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            None recorded.
                        </p>
                    </template>

                    <!-- Plain section -->
                    <div
                        v-else
                        :class="
                            mode === 'rapid'
                                ? 'grid gap-2'
                                : 'grid gap-4 sm:grid-cols-2'
                        "
                    >
                        <template
                            v-for="field in section.fields"
                            :key="field.key"
                        >
                            <FormFieldInput
                                v-if="evaluator.isVisible(field, null)"
                                :field="field"
                                :scope="null"
                                :path="field.key"
                                :compact="mode === 'rapid'"
                                :class="
                                    field.type === 'text' &&
                                    (field.rows ?? 1) > 1 &&
                                    mode !== 'rapid'
                                        ? 'sm:col-span-2'
                                        : ''
                                "
                            />
                        </template>
                    </div>
                </fieldset>
            </template>
        </section>

        <!-- Guided: page navigation -->
        <div
            v-if="mode === 'guided' && visiblePages.length > 1"
            class="flex items-center justify-between"
        >
            <Button
                type="button"
                variant="ghost"
                :disabled="pageIndex === 0"
                @click="pageIndex--"
            >
                <ChevronLeft class="size-4" />
                Back
            </Button>
            <Button
                v-if="pageIndex < visiblePages.length - 1"
                type="button"
                variant="outline"
                @click="pageIndex++"
            >
                Continue to
                {{ labelOf(visiblePages[pageIndex + 1], locale, 'title') }}
                <ChevronRight class="size-4" />
            </Button>
        </div>
    </div>
</template>
