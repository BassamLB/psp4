<script setup lang="ts">
import AdminSidebar from '@/components/sidebars/AdminSidebar.vue';
import DefaultSidebar from '@/components/sidebars/DefaultSidebar.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => (user.value as any)?.isAdmin === true);

// navigation items are defined elsewhere; this component simply delegates to AdminSidebar or DefaultSidebar
</script>

<template>
    <component :is="isAdmin ? AdminSidebar : DefaultSidebar" />
    <slot />
</template>
