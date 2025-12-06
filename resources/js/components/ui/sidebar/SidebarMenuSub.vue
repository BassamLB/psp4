<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <ul
    data-slot="sidebar-menu-sub"
    data-sidebar="menu-badge"
    :class="cn(
      'border-sidebar-border mx-3.5 flex min-w-0 translate-x-px flex-col gap-1 border-l px-2.5 py-0.5',
      // keep the sub-list visible even when sidebar is collapsed. the
      // sub-items will hide their text and display icons only via
      // SidebarMenuSubButton (see that component for collapsed behaviour)
      props.class,
    )"
  >
    <slot />
  </ul>
</template>
