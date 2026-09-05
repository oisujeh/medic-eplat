<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, UserSearch } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';

/**
 * The Master Patient Index check that runs while a registrar types: people
 * already on the register whom the details entered could describe.
 */
type Candidate = {
    id: number;
    file_number: string;
    name: string;
    sex: string;
    age: number | null;
    phone: string | null;
    lga: string | null;
    reasons: string[];
    score: number;
    level: 'auto' | 'review' | 'hint' | 'none';
    strong: boolean;
    url: string;
};

const props = defineProps<{
    details: {
        nin: string;
        phone: string;
        surname: string;
        first_name: string;
        date_of_birth: string;
        sex: string;
    };
    /** A record to leave out, e.g. the one being edited. */
    except?: number;
}>();

const candidates = ref<Candidate[]>([]);
const searching = ref(false);
let latest = 0;

function enough(): boolean {
    const d = props.details;

    return (
        d.nin.replace(/\s+/g, '').length === 11 ||
        d.phone.replace(/\D+/g, '').length >= 7 ||
        (d.surname.trim().length >= 2 && d.first_name.trim().length >= 2)
    );
}

const lookup = useDebounceFn(async () => {
    if (!enough()) {
        candidates.value = [];
        return;
    }

    const request = ++latest;
    searching.value = true;

    try {
        const query = new URLSearchParams({
            ...props.details,
            ...(props.except ? { except: String(props.except) } : {}),
        });
        const response = await fetch(`/registration/lookup?${query}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (request === latest && response.ok) {
            candidates.value = (await response.json()) as Candidate[];
        }
    } finally {
        if (request === latest) {
            searching.value = false;
        }
    }
}, 400);

watch(() => ({ ...props.details }), lookup, { deep: true });
</script>

<template>
    <div
        v-if="candidates.length"
        class="rounded-lg border border-amber-500/40 bg-amber-500/5 p-4"
        role="status"
    >
        <p class="flex items-center gap-2 text-sm font-medium">
            <AlertTriangle class="size-4 text-amber-600" />
            {{ candidates.length === 1 ? 'A patient' : candidates.length + ' patients' }}
            on the register may be this person
        </p>
        <p class="mt-1 text-xs text-muted-foreground">
            Check before registering again. Opening a record does not lose
            what you have typed here.
        </p>
        <ul class="mt-3 flex flex-col divide-y divide-border">
            <li
                v-for="c in candidates"
                :key="c.id"
                class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
            >
                <div class="flex flex-col gap-0.5">
                    <span class="font-medium">
                        {{ c.name }}
                        <span
                            v-if="c.strong"
                            class="ml-1 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-400"
                            >{{
                                c.level === 'auto'
                                    ? 'Almost certainly the same person'
                                    : 'Probably the same person'
                            }}</span
                        >
                    </span>
                    <span class="text-xs text-muted-foreground">
                        <span class="font-mono">{{ c.file_number }}</span>
                        · {{ c.age !== null ? c.age + 'y ' : '' }}{{ c.sex
                        }}{{ c.lga ? ' · ' + c.lga : '' }}
                        · {{ c.reasons.join(', ') }}
                    </span>
                </div>
                <Link
                    :href="c.url"
                    target="_blank"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    <UserSearch class="size-3.5" />
                    Open record
                </Link>
            </li>
        </ul>
    </div>
</template>
