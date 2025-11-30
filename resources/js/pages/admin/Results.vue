<script setup lang="ts">
import { ref, onMounted } from 'vue';

import AdminLayout from '@/layouts/AdminLayout.vue';

interface Child {
  type: string;
  id: number | string;
  name?: string;
  votes?: number;
}
const children = ref<Child[]>([]);
const labels = ref([]);
const data = ref([]);
const colors = ref([]);
const activeLevel = ref<string | null>(null);
import type { Ref } from 'vue';
const activeId: Ref<string | number | null> = ref(null);

const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chart: any = null;

const sciFiColors = ['#00f5ff', '#6ee7b7', '#7c3aed', '#ff6b6b', '#ffd166', '#06b6d4'];

function isDarkMode() {
  try {
    return document.documentElement.classList.contains('dark');
  } catch {
    return false;
  }
}

function normalizeHex(hex: string): string | null {
  if (!hex) return null;
  if (hex.startsWith('#')) hex = hex.slice(1);
  if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
  if (/^[0-9a-fA-F]{6}$/.test(hex)) return '#' + hex.toLowerCase();
  return null;
}

function shadeHex(hex: string, percent: number): string {
  // percent: -100..100
  const h = normalizeHex(hex);
  if (!h) return hex || '#0ea5a4';
  const num = parseInt(h.slice(1), 16);
  let r = (num >> 16) + Math.round(2.55 * percent);
  let g = ((num >> 8) & 0x00FF) + Math.round(2.55 * percent);
  let b = (num & 0x0000FF) + Math.round(2.55 * percent);
  r = Math.max(0, Math.min(255, r));
  g = Math.max(0, Math.min(255, g));
  b = Math.max(0, Math.min(255, b));
  return '#' + ( (1 << 24) + (r << 16) + (g << 8) + b ).toString(16).slice(1);
}

function borderColorFor(hex: string): string {
  const h = normalizeHex(hex);
  if (!h) return '#071024';
  return isDarkMode() ? shadeHex(h, -25) : shadeHex(h, -40);
}

function typeLabel(type: string): string {
  switch (type) {
    case 'electoral_district':
      return 'الدائرة الانتخابية';
    case 'district':
      return 'المنطقة';
    case 'town':
      return 'البلدة';
    case 'station':
      return 'مركز الاقتراع';
    case 'candidate':
      return 'مرشح';
    default:
      return type.replace('_', ' ');
  }
}

function displayLevel() {
  if (!activeLevel.value) return 'جميع الدوائر الانتخابية';
  return typeLabel(activeLevel.value);
}

async function fetchData() {
  const params = new URLSearchParams();
  if (activeLevel.value) params.append('level', activeLevel.value);
  if (activeId.value) params.append('id', String(activeId.value));

  const res = await fetch(`/admin/results/data?${params.toString()}`);
  if (!res.ok) return;
  const json = await res.json();
  labels.value = json.labels || [];
  data.value = json.data || [];
  colors.value = json.colors || [];
  children.value = json.children || [];
  updateChart();
}

function selectTop() {
  activeLevel.value = null;
  activeId.value = null;
  fetchData();
}

function drill(child: { type: string; id: number | string; name?: string; votes?: number }) {
  if (child.type === 'electoral_district') {
    activeLevel.value = 'electoral_district';
    activeId.value = child.id;
  } else if (child.type === 'district') {
    activeLevel.value = 'district';
    activeId.value = child.id;
  } else if (child.type === 'town') {
    activeLevel.value = 'town';
    activeId.value = child.id;
  } else if (child.type === 'station') {
    activeLevel.value = 'station';
    activeId.value = child.id;
  } else if (child.type === 'candidate') {
    return;
  }
  fetchData();
}

async function initChart() {
  if (!chartCanvas.value) return;

    try {
    const ChartModule = await import('chart.js/auto');
    const Chart = ChartModule.default || ChartModule;

    const ctx = chartCanvas.value.getContext('2d');
    if (!ctx) {
      console.warn('Could not get 2D context for chart canvas.');
      return;
    }
    chart = new Chart(ctx, {
      type: 'doughnut',
      data: {
          labels: labels.value,
          datasets: [{
            data: data.value,
            backgroundColor: sciFiColors,
            borderColor: '#071024',
            borderWidth: 2,
          }]
        },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: isDarkMode() ? '#e6eef6' : '#0f172a', usePointStyle: true }
          },
          tooltip: {
            titleColor: isDarkMode() ? '#e6eef6' : '#0f172a',
            bodyColor: isDarkMode() ? '#071024' : '#0f172a',
            backgroundColor: isDarkMode() ? '#c0f0ff' : '#fff',
          }
        }
      }
    });
  } catch {
    console.warn('Chart.js not found. Run `npm install chart.js` to enable charts.');
  }
}

function updateChart() {
  if (!chart) return;
  chart.data.labels = labels.value;
  chart.data.datasets[0].data = data.value;
  // Use provided list colors if available, otherwise fall back to sciFiColors
  const bg = (colors.value && colors.value.length) ? colors.value.map(c => normalizeHex(c) || '#0ea5a4') : sciFiColors;
  chart.data.datasets[0].backgroundColor = bg;
  // compute border colors from provided colors for contrast in dark/light mode
  chart.data.datasets[0].borderColor = (colors.value && colors.value.length) ? colors.value.map(c => borderColorFor(c)) : ['#071024'];
  chart.update();
}

onMounted(async () => {
  await initChart();
  await fetchData();
});
</script>

<template>
  <AdminLayout>
    <div dir="rtl" class="p-6 min-h-[70vh] bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-slate-100 rounded-lg">
    <div class="flex gap-6">
      <aside class="w-80 bg-black/30 backdrop-blur-md rounded-lg p-4">
        <h2 class="text-lg font-semibold mb-3">التنقل</h2>
        <div class="space-y-2">
          <button
            class="w-full text-left px-3 py-2 rounded-md hover:bg-white/5 transition-colors"
            :class="{ 'bg-white/5': !activeLevel }"
            @click="selectTop()"
          >
            جميع الدوائر الانتخابية
          </button>
          <transition-group name="list" tag="div">
            <div v-for="child in children" :key="child.type+child.id" class="flex items-center justify-between px-3 py-2 rounded-md hover:bg-white/5 cursor-pointer"
              @click="drill(child)">
              <div>
                <div class="text-sm font-medium">{{ child.name }}</div>
                <div class="text-xs text-slate-400">{{ typeLabel(child.type) }}</div>
              </div>
              <div class="text-sm font-mono text-cyan-300">{{ child.votes }}</div>
            </div>
          </transition-group>
        </div>
      </aside>

      <main class="flex-1 bg-black/20 p-6 rounded-lg">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h1 class="text-2xl font-bold">النتائج</h1>
            <div class="text-sm text-slate-400">التنقيب: الدائرة الانتخابية → المنطقة → البلدة → مركز الاقتراع</div>
          </div>
          <div class="text-sm text-slate-300">المستوى: <span class="font-semibold">{{ displayLevel() }}</span></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <section class="p-4 bg-gradient-to-br from-[#061426] to-[#0b1530] rounded-lg shadow-lg border border-cyan-800/20">
            <canvas ref="chartCanvas" class="w-full h-64"></canvas>
          </section>

          <section class="p-4 bg-black/10 rounded-lg">
            <div v-if="children.length === 0" class="text-slate-400">لا توجد عناصر فرعية لهذا المستوى.</div>
            <ul class="space-y-2">
              <li v-for="c in children" :key="c.id" class="flex items-center justify-between px-3 py-2 rounded-md bg-white/2">
                <div>
                  <div class="font-medium">{{ c.name }}</div>
                  <div class="text-xs text-slate-400">{{ typeLabel(c.type) }}</div>
                </div>
                <div class="font-mono text-cyan-300">{{ c.votes }}</div>
              </li>
            </ul>
          </section>
        </div>
      </main>
    </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.list-enter-active, .list-leave-active {
  transition: all 300ms ease;
}
.list-enter-from { transform: translateY(-6px); opacity: 0 }
.list-enter-to { transform: translateY(0); opacity: 1 }
.list-leave-from { transform: translateY(0); opacity: 1 }
.list-leave-to { transform: translateY(6px); opacity: 0 }
</style>
