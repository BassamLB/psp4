<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

// Local UI-friendly ref that maps the `appearance` ref (which may be 'system')
// into a strict light|dark toggling state for this control.
const current = ref<'light' | 'dark'>(appearance.value === 'dark' ? 'dark' : (appearance.value === 'light' ? 'light' : (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')));

// applyTheme removed — `toggleTheme` uses the composable and localStorage

onMounted(() => {
  // Prefer the value stored in localStorage to avoid overwriting a saved
  // appearance preference during hydration/mount ordering.
  try {
    const saved = localStorage.getItem('appearance');
    if (saved === 'dark' || saved === 'light') {
      current.value = saved;
      return;
    }
  } catch {
    // ignore
  }

  // Fall back to the composable's current value (may be 'system')
  if (appearance.value === 'dark' || appearance.value === 'light') {
    current.value = appearance.value as 'light' | 'dark';
  }
});

function toggleTheme() {
  current.value = current.value === 'dark' ? 'light' : 'dark';
  updateAppearance(current.value);
}
</script>

<template>
  <button
    @click="toggleTheme"
    :aria-pressed="current === 'dark'"
    aria-label="تبديل السمة (فاتح / غامق)"
    class="inline-flex items-center gap-2 p-2 rounded hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-300"
    title="تبديل السمة"
  >
    <Sun v-if="current === 'light'" class="w-4 h-4 text-amber-500" />
    <Moon v-else class="w-4 h-4 text-slate-300" />
    <span class="sr-only">تبديل السمة</span>
  </button>
</template>

<style scoped>
button[aria-pressed="true"] {
  background-color: rgba(99,102,241,0.08); /* indigo-500/8 */
}

button:hover {
  background-color: rgba(99,102,241,0.06);
}
</style>
