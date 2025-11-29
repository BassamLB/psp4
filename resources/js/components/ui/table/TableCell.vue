<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <td
        v-bind="$attrs"
        :class="cn('p-4 align-middle [&:has([role=checkbox])]:pr-0', $attrs.class ?? '')"
    >
        <slot />
    </td>
</template>
