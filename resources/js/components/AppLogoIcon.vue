<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
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
</template>
