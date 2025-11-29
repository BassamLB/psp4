<script setup lang="ts">
import { inject, type Ref } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    value: string;
    class?: string;
}>();

const slots = defineSlots<{
    default(): any;
}>();

const selectValue = inject<Ref<string>>('selectValue');
const updateSelect = inject<(value: string, text: string) => void>('selectUpdate');

const handleClick = () => {
    if (updateSelect) {
        // Get text content from slot
        const textContent = slots.default?.()[0]?.children || props.value;
        updateSelect(props.value, String(textContent));
    }
};
</script>

<template>
    <div
        @click="handleClick"
        :class="
            cn(
                'relative flex w-full cursor-pointer select-none items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                selectValue === value && 'bg-accent',
                $props.class,
            )
        "
    >
        <slot />
    </div>
</template>
