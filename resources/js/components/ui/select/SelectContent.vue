<script setup lang="ts">
import { inject, type Ref } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
    class?: string;
}>();

const isOpen = inject<Ref<boolean>>('selectIsOpen');
</script>

<template>
    <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="transform scale-95 opacity-0"
        enter-to-class="transform scale-100 opacity-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="transform scale-100 opacity-100"
        leave-to-class="transform scale-95 opacity-0"
    >
        <div
            v-if="isOpen"
            :class="
                cn(
                    'absolute right-0 z-50 mt-1 min-w-[8rem] max-h-60 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md origin-top',
                    $props.class,
                )
            "
        >
            <slot />
        </div>
    </Transition>
</template>
