<script setup lang="ts">
import AdminSidebar from '@/components/sidebars/AdminSidebar.vue';
import DataEditorSidebar from '@/components/sidebars/DataEditorSidebar.vue';
import DefaultSidebar from '@/components/sidebars/DefaultSidebar.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => (user.value as any)?.isAdmin === true);
const isDataEditor = computed(() => (user.value as any)?.isDataEditor === true);

// Determine which sidebar to show based on user role
const sidebarComponent = computed(() => {
    if (isAdmin.value) return AdminSidebar;
    if (isDataEditor.value) return DataEditorSidebar;
    return DefaultSidebar;
});
</script>

<template>
    <component :is="sidebarComponent">
        <slot />
    </component>
</template>
