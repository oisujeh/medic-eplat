<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, ScrollText, Users } from '@lucide/vue';
import { index as auditIndex } from '@/routes/admin/audit';
import { edit as editFacility } from '@/routes/admin/facility';
import { index as usersIndex } from '@/routes/admin/users';

defineProps<{
    current: 'users' | 'facility' | 'audit';
}>();

const tabs = [
    { key: 'users', title: 'Staff accounts', href: usersIndex(), icon: Users },
    {
        key: 'facility',
        title: 'Facility profile',
        href: editFacility(),
        icon: Building2,
    },
    {
        key: 'audit',
        title: 'Audit trail',
        href: auditIndex(),
        icon: ScrollText,
    },
] as const;
</script>

<template>
    <nav
        class="flex gap-1 border-b border-border"
        aria-label="Administration sections"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition-colors"
            :class="
                tab.key === current
                    ? 'border-primary font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            :aria-current="tab.key === current ? 'page' : undefined"
        >
            <component :is="tab.icon" class="size-4" />
            {{ tab.title }}
        </Link>
    </nav>
</template>
