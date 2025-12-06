<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-full bg-sidebar-primary text-sidebar-primary-foreground shadow-md transition-all duration-200 group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:size-6 group-data-[collapsible=icon]:shadow-none"
    >
        <AppLogoIcon class="fill-current text-foreground" />
    </div>
    <div class="mr-1 grid flex-1 text-right text-sm group-data-[collapsible=icon]:hidden group-data-[collapsible=icon]:mr-0">
        <span class="mb-0.5 truncate leading-tight text-base font-bold">حزب التقدمي الاشتراكي</span>
        <span class="mb-0.5 leading-tight">لـــــــــجــــــــنـــــــة الـــــــمــــــــعـــــــلـــــــومــــــاتــــــيــــــة</span>
    </div>
</template>
