<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <div class="relative w-full overflow-auto">
        <table
            v-bind="$attrs"
            :class="cn('w-full caption-bottom text-sm', $attrs.class ?? '')"
        >
            <slot />
        </table>
    </div>
</template>
