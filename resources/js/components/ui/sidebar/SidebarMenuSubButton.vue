<script setup lang="ts">
import type { PrimitiveProps } from 'reka-ui'
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip'
import { useSidebar } from './utils'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { Primitive } from 'reka-ui'

const props = withDefaults(defineProps<PrimitiveProps & {
  size?: 'sm' | 'md'
  isActive?: boolean
  class?: HTMLAttributes['class']
  tooltip?: string | object
}>(), {
  as: 'a',
  size: 'md',
})
const { isMobile, state } = useSidebar()
</script>

<template>
  <template v-if="!props.tooltip">
    <Primitive
    data-slot="sidebar-menu-sub-button"
    data-sidebar="menu-sub-button"
    :as="as"
    :as-child="asChild"
    :data-size="size"
    :data-active="isActive"
      v-bind="$attrs"
      :class="cn(
      'text-sidebar-foreground ring-sidebar-ring hover:bg-sidebar-accent hover:text-sidebar-accent-foreground active:bg-sidebar-accent active:text-sidebar-accent-foreground [&>svg]:text-sidebar-accent-foreground flex h-7 min-w-0 -translate-x-px items-center gap-2 overflow-hidden rounded-md px-2 outline-hidden focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0',
      'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground',
      size === 'sm' && 'text-xs',
      size === 'md' && 'text-sm',
      // when the sidebar is collapsed to icon-only we want the sub buttons to
      // remain visible but compact. hide their text content and center the
      // icon so the sub-items are still discoverable when collapsed.
      'group-data-[collapsible=icon]:justify-center',
      'group-data-[collapsible=icon]:px-1',
      'group-data-[collapsible=icon]:[&>span:last-child]:hidden',
      props.class,
    )"
    >
    <slot />
  </Primitive>
  </template>

  <template v-else>
    <Tooltip>
      <TooltipTrigger as-child>
        <Primitive
          data-slot="sidebar-menu-sub-button"
          data-sidebar="menu-sub-button"
          :as="as"
          :as-child="asChild"
          :data-size="size"
          :data-active="isActive"
          v-bind="$attrs"
          :class="cn(
            'text-sidebar-foreground ring-sidebar-ring hover:bg-sidebar-accent hover:text-sidebar-accent-foreground active:bg-sidebar-accent active:text-sidebar-accent-foreground [&>svg]:text-sidebar-accent-foreground flex h-7 min-w-0 -translate-x-px items-center gap-2 overflow-hidden rounded-md px-2 outline-hidden focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0',
            'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground',
            size === 'sm' && 'text-xs',
            size === 'md' && 'text-sm',
            'group-data-[collapsible=icon]:justify-center',
            'group-data-[collapsible=icon]:px-1',
            'group-data-[collapsible=icon]:[&>span:last-child]:hidden',
            props.class,
          )"
        >
          <slot />
        </Primitive>
      </TooltipTrigger>
      <TooltipContent
        side="right"
        align="center"
        :hidden="state !== 'collapsed' || isMobile"
      >
        {{ props.tooltip as string }}
      </TooltipContent>
    </Tooltip>
  </template>
</template>
