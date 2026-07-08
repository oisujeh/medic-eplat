<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import * as LucideIcons from '@lucide/vue';
import { BookOpen, FolderGit2, LayoutGrid } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, NavModule, SharedData } from '@/types';

const page = usePage<SharedData>();

const icons = LucideIcons as unknown as Record<string, LucideIcon>;

function resolveIcon(name: string | null): LucideIcon {
    return (name && icons[name]) || LayoutGrid;
}

// The modules a user may see are driven by their roles and shared from the
// server, so the sidebar reflects each user's access without any hardcoding.
const mainNavItems = computed<NavItem[]>(() =>
    (page.props.auth.modules ?? []).map((module: NavModule) => ({
        title: module.name,
        href: module.href,
        icon: resolveIcon(module.icon),
    })),
);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
