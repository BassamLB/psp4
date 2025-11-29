<script setup lang="ts">
import { provide, ref, watch } from 'vue';

const props = defineProps<{
    modelValue?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const value = ref(props.modelValue || '');
const isOpen = ref(false);
const selectedText = ref('');

watch(() => props.modelValue, (newValue) => {
    if (newValue !== undefined) {
        value.value = newValue;
    }
});

provide('selectValue', value);
provide('selectIsOpen', isOpen);
provide('selectSelectedText', selectedText);
provide('selectUpdate', (newValue: string, text: string) => {
    value.value = newValue;
    selectedText.value = text;
    emit('update:modelValue', newValue);
    isOpen.value = false;
});
provide('selectToggle', () => {
    isOpen.value = !isOpen.value;
});
</script>

<template>
    <div class="relative">
        <slot />
    </div>
</template>
