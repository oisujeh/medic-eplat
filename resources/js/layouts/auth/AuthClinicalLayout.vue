<template>
    <div
        class="login-clinical grid min-h-dvh grid-cols-1 bg-[var(--cl-navy)] lg:grid-cols-[1.15fr_1fr]"
    >
        <div
            class="login-brand relative hidden flex-col justify-between overflow-hidden px-16 py-[4.5rem] text-[var(--cl-paper)] lg:flex"
        >
            <div
                class="relative z-10 flex items-center gap-[0.7rem] font-[family-name:var(--cl-font-display)] text-[1.05rem] font-semibold"
            >
                <svg
                    viewBox="0 0 32 32"
                    fill="none"
                    class="h-[30px] w-[30px]"
                    aria-hidden="true"
                >
                    <path
                        d="M2 16h6l3-9 6 18 3-9h10"
                        stroke="#7FBFA8"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                Medic-EPlat HIS
            </div>

            <div class="relative z-10">
                <div
                    class="mb-[1.1rem] font-[family-name:var(--cl-font-mono)] text-[0.7rem] tracking-[0.14em] text-[rgba(225,235,238,0.55)] uppercase"
                >
                    Facility server · v2.4.0 · offline-capable
                </div>
                <h1
                    class="mb-[1.1rem] max-w-[13ch] font-[family-name:var(--cl-font-display)] text-[2.6rem] leading-[1.12] font-medium text-white"
                >
                    Hospital records, built for the ward, not the boardroom.
                </h1>
                <p
                    class="max-w-[34ch] text-[0.95rem] leading-relaxed text-[rgba(225,235,238,0.68)]"
                >
                    Electronic medical records, disease surveillance, and
                    operations reporting in one system that keeps working when
                    the internet doesn't.
                </p>

                <div
                    class="mt-10 flex gap-[2.2rem] border-t border-white/10 pt-[1.6rem]"
                >
                    <div v-for="stat in vitals" :key="stat.label">
                        <div
                            class="mb-[0.4rem] font-[family-name:var(--cl-font-mono)] text-[0.66rem] tracking-[0.08em] text-[rgba(225,235,238,0.45)] uppercase"
                        >
                            {{ stat.label }}
                        </div>
                        <div
                            class="flex items-baseline gap-[0.35rem] font-[family-name:var(--cl-font-mono)] text-[1.3rem] font-medium text-white"
                        >
                            {{ stat.value }}
                            <span
                                v-if="stat.trend"
                                class="text-[0.7rem]"
                                :class="
                                    stat.down
                                        ? 'text-[#D99A8A]'
                                        : 'text-[#7FBFA8]'
                                "
                            >
                                {{ stat.trend }}
                            </span>
                            <span v-if="stat.unit" class="text-[0.9rem]">{{
                                stat.unit
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="relative z-10 font-[family-name:var(--cl-font-mono)] text-[0.72rem] text-[rgba(225,235,238,0.4)]"
            >
                LAGOS GENERAL · NODE-04 · LAST SYNCED 09:14
            </div>
        </div>

        <div
            class="flex items-center justify-center bg-[var(--cl-paper)] p-8 lg:p-12"
        >
            <div class="w-full max-w-[380px]">
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
const vitals = [
    { label: 'Admitted today', value: '47', trend: '↑ 12%' },
    { label: 'Bed occupancy', value: '82', unit: '%' },
    { label: 'Active alerts', value: '3', trend: '↑ malaria', down: true },
    { label: 'Avg. wait time', value: '22', unit: 'm' },
];
</script>

<style scoped>
.login-clinical {
    --cl-navy: #0f2a3d;
    --cl-navy-light: #1b3f57;
    --cl-paper: #f7f5f0;
    --cl-ink: #1a1f23;
    --cl-ink-soft: #5c6670;
    --cl-ink-faint: #94a0a8;
    --cl-teal: #3d7a6b;
    --cl-teal-light: #e3eeea;
    --cl-teal-deep: #25524a;
    --cl-line: rgba(15, 42, 61, 0.12);
    --cl-white: #ffffff;
    --cl-font-display: 'Source Serif 4', Georgia, serif;
    --cl-font-mono: 'JetBrains Mono', monospace;

    /* Pin shadcn tokens to their light values so the login keeps a fixed
       paper/navy look regardless of the app's dark-mode toggle. */
    --background: hsl(0 0% 100%);
    --foreground: hsl(0 0% 3.9%);
    --primary: hsl(0 0% 9%);
    --primary-foreground: hsl(0 0% 98%);
    --muted-foreground: hsl(0 0% 45.1%);
    --border: hsl(0 0% 92.8%);
    --input: hsl(0 0% 89.8%);
    --ring: hsl(0 0% 3.9%);
    --destructive: hsl(0 84.2% 60.2%);
}

.login-brand {
    background:
        radial-gradient(
            circle at 18% 22%,
            rgba(255, 255, 255, 0.045) 0%,
            transparent 38%
        ),
        var(--cl-navy);
}

.login-brand::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: repeating-linear-gradient(
        0deg,
        transparent 0px,
        transparent 31px,
        rgba(255, 255, 255, 0.035) 31px,
        rgba(255, 255, 255, 0.035) 32px
    );
}
</style>
