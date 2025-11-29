<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <tr
        v-bind="$attrs"
        :class="cn('border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted', $attrs.class ?? '')"
    >
        <slot />
    </tr>
</template>
