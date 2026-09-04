<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Construction } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
</script>

<template>
    <Head title="Setup in progress" />

    <div
        class="flex min-h-dvh flex-col items-center justify-center bg-background px-4 text-foreground"
    >
        <div class="w-full max-w-md text-center">
            <div
                class="mx-auto mb-6 flex size-12 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
            >
                <AppLogoIcon
                    class="size-7 fill-current text-white dark:text-black"
                />
            </div>

            <div
                class="mx-auto mb-4 flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
            >
                <Construction class="size-5" />
            </div>

            <h1 class="text-xl font-semibold tracking-tight">
                This facility is still being set up
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                An administrator has not yet completed the first-run setup. Once
                the facility profile is in place you will be able to sign in and
                get to work.
            </p>

            <Button as-child variant="outline" class="mt-6">
                <Link :href="logout()" as="button">Sign out</Link>
            </Button>
        </div>
    </div>
</template>
