<script setup lang="ts">
import { CollapsibleContent, type CollapsibleContentProps } from 'reka-ui'

const props = defineProps<CollapsibleContentProps>()
</script>

<template>
  <!--
    Add a smooth height + opacity transition around the collapsible content.
    The underlying `CollapsibleContent` from reka-ui controls visibility
    (via v-show or similar) so the Vue <Transition> will animate that change.
  -->
  <Transition
    enter-from-class="opacity-0 max-h-0"
    enter-active-class="transition-[max-height,opacity] duration-200 ease-linear"
    enter-to-class="opacity-100 max-h-[36rem]"
    leave-from-class="opacity-100 max-h-[36rem]"
    leave-active-class="transition-[max-height,opacity] duration-200 ease-linear"
    leave-to-class="opacity-0 max-h-0"
  >
    <CollapsibleContent
      data-slot="collapsible-content"
      v-bind="props"
    >
      <slot />
    </CollapsibleContent>
  </Transition>
</template>
