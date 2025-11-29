<script setup lang="ts">
import { inject, computed, type Ref } from 'vue';

defineProps<{
    placeholder?: string;
}>();

const value = inject<Ref<string>>('selectValue');
const selectedText = inject<Ref<string>>('selectSelectedText', computed(() => ''));
</script>

<template>
    <span class="pointer-events-none">
        {{ selectedText || placeholder }}
    </span>
</template>
