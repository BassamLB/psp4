<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Toast from '@/components/Toast.vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
    [key: string]: string | undefined;
}

defineProps<Props>();

const page = usePage();
const isOpen = page.props.sidebarOpen;

const toast = ref<InstanceType<typeof Toast> | null>(null);

watch(() => page.props.flash as FlashMessages, (flash) => {
    if (!flash) return;
    if (flash.success) toast.value?.addToast(flash.success, 'success', 4000);
    if (flash.error) toast.value?.addToast(flash.error, 'error', 5000);
    if (flash.warning) toast.value?.addToast(flash.warning, 'warning', 4000);
    if (flash.info) toast.value?.addToast(flash.info, 'info', 4000);
}, { immediate: true });
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
        <Toast ref="toast" />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <slot />
        <Toast ref="toast" />
    </SidebarProvider>
</template>

<style>
/* Micro settle animation for list items (used by admin settings pages) */
.settle-enter-from {
    opacity: 0;
    transform: translateY(16px) scale(0.98);
}
.settle-enter-to {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.settle-enter-active {
    transition: transform 300ms cubic-bezier(.22,.9,.3,1), opacity 300ms cubic-bezier(.22,.9,.3,1);
}

/* Appear hooks — run on initial mount when transition-group has 'appear' */
.settle-appear-from {
    opacity: 0;
    transform: translateY(16px) scale(0.98);
}
.settle-appear-to {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.settle-appear-active {
    transition: transform 300ms cubic-bezier(.22,.9,.3,1), opacity 300ms cubic-bezier(.22,.9,.3,1);
}
.settle-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.settle-leave-to {
    opacity: 0;
    transform: translateY(10px) scale(0.99);
}
.settle-leave-active {
    transition: transform 220ms ease, opacity 220ms ease;
}

/* Slight hover 'pop' to indicate interactivity */
.settle-item {
    will-change: transform, opacity;
    transition: transform 180ms ease, box-shadow 180ms ease;
}

/* Respect operating system reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .settle-enter-active,
    .settle-leave-active,
    .settle-item {
        transition: none !important;
        transform: none !important;
    }
}
</style>
