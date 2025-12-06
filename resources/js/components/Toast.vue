<template>
  <div class="fixed top-4 left-4 z-50 pointer-events-none">
    <TransitionGroup
      name="list"
      tag="div"
      class="space-y-3"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast-wrapper"
      >
        <div
          class="w-96 max-w-full shadow-lg rounded-lg pointer-events-auto"
          :class="getBgColor(toast.type)"
        >
        <div class="rounded-lg shadow-xs overflow-hidden">
          <div class="p-4">
            <div class="flex items-start">
              <div class="shrink-0">
                <svg
                  v-if="toast.type === 'success'"
                  class="h-6 w-6 text-green-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <svg
                  v-else-if="toast.type === 'error'"
                  class="h-6 w-6 text-red-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <svg
                  v-else-if="toast.type === 'warning'"
                  class="h-6 w-6 text-yellow-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </svg>
                <svg
                  v-else
                  class="h-6 w-6 text-blue-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>
              <div class="mr-3 w-0 flex-1 pt-0.5">
                <p class="text-sm font-medium" :class="getTextColor(toast.type)">
                  {{ toast.message }}
                </p>
              </div>
              <div class="mr-4 shrink-0 flex">
                <button
                  @click="removeToast(toast.id)"
                  class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
                  :class="getCloseButtonColor(toast.type)"
                >
                  <span class="sr-only">Close</span>
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path
                      fill-rule="evenodd"
                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    </TransitionGroup>
  </div>
 </template>

<script setup lang="ts">
import { ref } from 'vue';

type Toast = {
  id: number;
  message: string;
  type: string;
};

const toasts = ref<Toast[]>([]);
let nextId = 0;

const addToast = (message: string, type = 'success', duration = 5000) => {
  const id = nextId++;
  toasts.value.push({ id, message, type });

  setTimeout(() => {
    removeToast(id);
  }, duration);
};

const removeToast = (id: number) => {
  const index = toasts.value.findIndex(toast => toast.id === id);
  if (index > -1) {
    toasts.value.splice(index, 1);
  }
};

const getBgColor = (type: string) => {
  switch (type) {
    case 'success':
      return 'bg-card';
    case 'error':
      return 'bg-red-50 dark:bg-red-900';
    case 'warning':
      return 'bg-yellow-50 dark:bg-yellow-900';
    default:
      return 'bg-blue-50 dark:bg-blue-900';
  }
};

const getTextColor = (type: string) => {
  switch (type) {
    case 'success':
      return 'text-foreground';
    case 'error':
      return 'text-red-800 dark:text-red-200';
    case 'warning':
      return 'text-yellow-800 dark:text-yellow-200';
    default:
      return 'text-blue-800 dark:text-blue-200';
  }
};

const getCloseButtonColor = (type: string) => {
  switch (type) {
    case 'success':
      return 'text-green-500 hover:text-green-600 focus:ring-green-500';
    case 'error':
      return 'text-red-500 hover:text-red-600 focus:ring-red-500';
    case 'warning':
      return 'text-yellow-500 hover:text-yellow-600 focus:ring-yellow-500';
    default:
      return 'text-blue-500 hover:text-blue-600 focus:ring-blue-500';
  }
};

// Expose addToast method for parent component
defineExpose({ addToast });
</script>

<style scoped>
.toast-wrapper {
  transition: all 0.3s ease;
}

.list-move {
  transition: all 0.3s ease;
}

.list-enter-active {
  transition: all 0.3s ease;
}

.list-leave-active {
  transition: all 0.3s ease;
  position: absolute;
}

.list-enter-from {
  opacity: 0;
  transform: translateX(-100%);
}

.list-leave-to {
  opacity: 0;
  transform: translateX(-100%);
}
</style>
