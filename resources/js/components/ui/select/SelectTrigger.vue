<script setup lang="ts">
import { inject, type Ref } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
    class?: string;
}>();

const toggle = inject<() => void>('selectToggle');

const handleClick = () => {
    if (toggle) {
        toggle();
    }
};
</script>

<template>
    <button
        type="button"
        @click="handleClick"
        :class="
            cn(
                'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1',
                $props.class,
            )
        "
    >
        <slot />
        <svg
            class="h-4 w-4 opacity-50"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <polyline points="6 9 12 15 18 9" />
        </svg>
    </button>
</template>
