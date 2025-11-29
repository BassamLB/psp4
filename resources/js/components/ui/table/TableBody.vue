<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <tbody
        v-bind="$attrs"
        :class="cn('[&_tr:last-child]:border-0', $attrs.class ?? '')"
    >
        <slot />
    </tbody>
</template>
