<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Check } from '@lucide/vue';
import { computed, reactive } from 'vue';
import DataQualityNav from '@/components/data-quality/DataQualityNav.vue';
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
import { update as updateRule } from '@/routes/data-quality/rules';

type ParameterField = {
    key: string;
    label: string;
    type: string;
    suffix?: string;
    min?: number;
    max?: number;
};

type RuleRow = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    dimension: string;
    dimension_label: string;
    severity: string;
    owner_role: string | null;
    parameters: Record<string, string | number>;
    parameter_fields: ParameterField[];
    runs_on_write: boolean;
    is_active: boolean;
    open_issues_count: number;
    version: number;
    missing_check: boolean;
};

type Option = { value: string; label: string };

const props = defineProps<{
    rules: RuleRow[];
    dimensions: Option[];
    severities: Option[];
    roles: Option[];
    can: { manage_rules: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Data quality', href: '/data-quality' }],
    },
});

const NOBODY = 'nobody';

/**
 * One editable copy per rule, so a half-made change never reads as saved.
 */
const drafts = reactive(
    Object.fromEntries(
        props.rules.map((rule) => [
            rule.id,
            {
                severity: rule.severity,
                owner_role: rule.owner_role ?? NOBODY,
                is_active: rule.is_active,
                parameters: { ...rule.parameters },
            },
        ]),
    ) as Record<
        number,
        {
            severity: string;
            owner_role: string;
            is_active: boolean;
            parameters: Record<string, string | number>;
        }
    >,
);

const saving = reactive<Record<number, boolean>>({});

function isDirty(rule: RuleRow): boolean {
    const draft = drafts[rule.id];

    return (
        draft.severity !== rule.severity ||
        draft.owner_role !== (rule.owner_role ?? NOBODY) ||
        draft.is_active !== rule.is_active ||
        rule.parameter_fields.some(
            (field) =>
                String(draft.parameters[field.key] ?? '') !==
                String(rule.parameters[field.key] ?? ''),
        )
    );
}

function save(rule: RuleRow) {
    const draft = drafts[rule.id];
    saving[rule.id] = true;

    router.patch(
        updateRule(rule.id).url,
        {
            severity: draft.severity,
            owner_role: draft.owner_role === NOBODY ? null : draft.owner_role,
            is_active: draft.is_active,
            parameters: draft.parameters,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saving[rule.id] = false;
            },
        },
    );
}

function reset(rule: RuleRow) {
    drafts[rule.id] = {
        severity: rule.severity,
        owner_role: rule.owner_role ?? NOBODY,
        is_active: rule.is_active,
        parameters: { ...rule.parameters },
    };
}

const grouped = computed(() =>
    props.dimensions.map((dimension) => ({
        ...dimension,
        rules: props.rules.filter((rule) => rule.dimension === dimension.value),
    })),
);

const activeCount = computed(
    () => props.rules.filter((rule) => rule.is_active).length,
);
</script>

<template>
    <Head title="Data quality rules" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Data quality rules
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    What each rule looks for, how serious a failure is and whose
                    queue it lands in. Changing what a rule means opens a new
                    version; issues already raised keep the version they were
                    raised under.
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card px-4 py-2">
                <p class="text-xs text-muted-foreground">Rules in force</p>
                <p class="text-xl font-semibold tabular-nums">
                    {{ activeCount }}
                    <span class="text-xs font-medium text-muted-foreground"
                        >of {{ rules.length }}</span
                    >
                </p>
            </div>
        </div>

        <DataQualityNav current="rules" />

        <p
            v-if="!can.manage_rules"
            class="rounded-lg border border-border bg-muted/40 px-4 py-2 text-sm text-muted-foreground"
        >
            You can see how the rules are set, but only a role with the
            data-quality rules permission can change them.
        </p>

        <section
            v-for="dimension in grouped"
            :key="dimension.value"
            class="flex flex-col gap-3"
        >
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ dimension.label }}
            </h2>

            <div
                v-for="rule in dimension.rules"
                :key="rule.id"
                class="rounded-xl border border-border bg-card p-4"
                :class="rule.is_active ? '' : 'opacity-70'"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="max-w-2xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium">{{ rule.name }}</h3>
                            <span
                                class="rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                >v{{ rule.version }}</span
                            >
                            <span
                                v-if="!rule.runs_on_write"
                                class="rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                >nightly sweep</span
                            >
                            <span
                                v-if="rule.missing_check"
                                class="inline-flex items-center gap-1 rounded-md bg-red-500/10 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-400"
                            >
                                <AlertTriangle class="size-3" />
                                No check in this version of the software
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ rule.description }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-muted-foreground">Open</p>
                        <p class="text-lg font-semibold tabular-nums">
                            {{ rule.open_issues_count }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    :class="can.manage_rules ? '' : 'pointer-events-none'"
                >
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Severity</Label>
                        <Select v-model="drafts[rule.id].severity">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in severities"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Whose queue</Label>
                        <Select v-model="drafts[rule.id].owner_role">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NOBODY"
                                    >Everyone with the module</SelectItem
                                >
                                <SelectItem
                                    v-for="role in roles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div
                        v-for="field in rule.parameter_fields"
                        :key="field.key"
                        class="grid gap-1.5"
                    >
                        <Label class="text-xs"
                            >{{ field.label
                            }}<template v-if="field.suffix">
                                ({{ field.suffix }})</template
                            ></Label
                        >
                        <Input
                            v-model="drafts[rule.id].parameters[field.key]"
                            :type="field.type === 'number' ? 'number' : 'text'"
                            :min="field.min"
                            :max="field.max"
                        />
                    </div>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <label
                        class="flex items-center gap-2 text-sm"
                        :class="
                            can.manage_rules
                                ? 'cursor-pointer'
                                : 'pointer-events-none opacity-70'
                        "
                    >
                        <input
                            v-model="drafts[rule.id].is_active"
                            type="checkbox"
                            class="size-4 rounded border-input"
                        />
                        Rule is in force
                    </label>

                    <div v-if="can.manage_rules" class="flex gap-2">
                        <Button
                            v-if="isDirty(rule)"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="reset(rule)"
                        >
                            Discard
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :disabled="!isDirty(rule) || saving[rule.id]"
                            @click="save(rule)"
                        >
                            <Check class="size-4" />
                            {{ isDirty(rule) ? 'Save changes' : 'Saved' }}
                        </Button>
                    </div>
                </div>
            </div>

            <p
                v-if="!dimension.rules.length"
                class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
            >
                No rules in this dimension.
            </p>
        </section>
    </div>
</template>
