<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <thead
        v-bind="$attrs"
        :class="cn('[&_tr]:border-b', $attrs.class ?? '')"
    >
        <slot />
    </thead>
</template>
