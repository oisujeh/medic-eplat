<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthClinicalLayout from '@/layouts/auth/AuthClinicalLayout.vue';
import { store } from '@/routes/login';
import type { SharedData } from '@/types';

defineOptions({ layout: AuthClinicalLayout });

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<SharedData>();
const facilityCode = computed(
    () => page.props.facility?.code ?? 'Facility not yet set up',
);
</script>

<template>
    <Head title="Log in" />

    <div
        class="mb-[1.8rem] flex justify-between border-b border-[var(--cl-line)] pb-[0.9rem] font-[family-name:var(--cl-font-mono)] text-[0.68rem] tracking-[0.12em] text-[var(--cl-ink-faint)] uppercase"
    >
        <span>Staff access</span>
        <span>{{ facilityCode }}</span>
    </div>

    <h2
        class="font-[family-name:var(--cl-font-display)] text-[1.5rem] font-medium text-[var(--cl-navy)]"
    >
        Sign in to your station
    </h2>
    <p class="mt-1 mb-8 text-[0.85rem] text-[var(--cl-ink-soft)]">
        Enter your credentials to access patient records and ward tools.
    </p>

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-[var(--cl-teal-deep)]"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[1.1rem]"
    >
        <div class="grid gap-[0.4rem]">
            <Label
                for="login"
                class="text-[0.8rem] font-medium text-[var(--cl-ink-soft)]"
            >
                Email or username
            </Label>
            <Input
                id="login"
                type="text"
                name="login"
                required
                autofocus
                :tabindex="1"
                autocomplete="username"
                placeholder="email@example.com or username"
                class="h-auto rounded-[3px] border-[var(--cl-line)] bg-[var(--cl-white)] px-[0.85rem] py-[0.7rem] text-[0.9rem] text-[var(--cl-ink)] placeholder:text-[var(--cl-ink-faint)] focus-visible:border-[var(--cl-teal)] focus-visible:ring-[var(--cl-teal-light)] dark:bg-[var(--cl-white)]"
            />
            <InputError :message="errors.login" />
        </div>

        <div class="grid gap-[0.4rem]">
            <div class="flex items-center justify-between">
                <Label
                    for="password"
                    class="text-[0.8rem] font-medium text-[var(--cl-ink-soft)]"
                >
                    Password
                </Label>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Password"
                class="h-auto rounded-[3px] border-[var(--cl-line)] bg-[var(--cl-white)] px-[0.85rem] py-[0.7rem] text-[0.9rem] text-[var(--cl-ink)] placeholder:text-[var(--cl-ink-faint)] focus-visible:border-[var(--cl-teal)] focus-visible:ring-[var(--cl-teal-light)] dark:bg-[var(--cl-white)]"
            />
            <InputError :message="errors.password" />
        </div>

        <Label
            for="remember"
            class="flex items-center gap-[0.6rem] text-[0.8rem] font-normal text-[var(--cl-ink-soft)]"
        >
            <Checkbox
                id="remember"
                name="remember"
                :tabindex="3"
                class="rounded-[3px] border-[var(--cl-ink-faint)] focus-visible:border-[var(--cl-teal)] focus-visible:ring-[var(--cl-teal-light)] data-[state=checked]:border-[var(--cl-navy)] data-[state=checked]:bg-[var(--cl-navy)] data-[state=checked]:text-white"
            />
            <span>Remember me</span>
        </Label>

        <Button
            type="submit"
            class="mt-[0.4rem] h-auto w-full rounded-[3px] bg-[var(--cl-navy)] py-[0.8rem] text-[0.88rem] font-medium text-white hover:bg-[var(--cl-navy-light)]"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Sign in
        </Button>
    </Form>

    <div
        class="mt-[1.4rem] text-center text-[0.8rem] text-[var(--cl-ink-faint)]"
    >
        Forgot your password? Ask your facility IT administrator.
    </div>

    <div
        class="mt-[1.8rem] inline-flex items-center gap-[0.4rem] rounded-full bg-[var(--cl-teal-light)] px-[0.6rem] py-[0.3rem] font-[family-name:var(--cl-font-mono)] text-[0.68rem] text-[var(--cl-teal-deep)]"
    >
        <span class="h-[6px] w-[6px] rounded-full bg-[var(--cl-teal)]" />
        Works offline — data syncs when connection returns
    </div>
</template>
