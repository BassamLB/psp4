<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <th
        v-bind="$attrs"
        :class="cn('h-12 px-4 text-right align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0', $attrs.class ?? '')"
    >
        <slot />
    </th>
</template>
