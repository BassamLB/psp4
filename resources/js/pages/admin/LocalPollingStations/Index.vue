<script setup lang="ts">
import { Link, router, Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { ChevronLeft } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import LocalPollingStationEditModal from '@/components/LocalPollingStationEditModal.vue';

const props = defineProps<{ stations: { data: Array<any>; [key: string]: any }; towns: Array<any>; elections: Array<any> }>();

const allStations = ref([...props.stations.data]);
console.log(allStations);
const isLoading = ref(false);
const hasMore = ref(!!props.stations.next_page_url);

// Drilldown state (electoral districts -> district -> town -> stations)
const children = ref<Array<{ id: number | string; name: string; type: string; stations_count?: number }>>([]);
// stack used to track drill navigation so we can return one level up
const drillStack = ref<Array<{ level: string | null; id: number | string | null }>>([]);
const activeLevel = ref<"electoral_district" | "district" | "town" | null>(null);
const activeId = ref<string | number | null>(null);
const selectedStations = ref<Array<{ id: number | string; name: string; type: string }>>([]); // when a town is selected we'll populate stations here

async function loadMore() {
  if (isLoading.value || !hasMore.value) return;

  isLoading.value = true;

  router.get(props.stations.next_page_url, {}, {
    preserveState: true,
    preserveScroll: true,
    only: ['stations'],
    onSuccess: (page) => {
      const newStations = page.props.stations as { data: Array<any>; next_page_url?: string | null };
      allStations.value.push(...newStations.data);
      hasMore.value = !!newStations.next_page_url;
      isLoading.value = false;
    },
    onError: () => {
      isLoading.value = false;
    }
  });
}

function handleScroll() {
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const scrollHeight = document.documentElement.scrollHeight;
  const clientHeight = document.documentElement.clientHeight;

  if (scrollTop + clientHeight >= scrollHeight - 200 && !isLoading.value && hasMore.value) {
    loadMore();
  }
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  // load top-level electoral districts for drilldown
  fetchDrillChildren();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});

async function fetchDrillChildren() {
  const params = new URLSearchParams();
  if (activeLevel.value) params.append('level', activeLevel.value);
  if (activeId.value) params.append('id', activeId.value.toString());

  const res = await fetch(`/admin/results/data?${params.toString()}`);
  if (!res.ok) return;
  const json = await res.json();
  children.value = json.children || [];

  // If we drilled to town level, populate selectedStations from the children that are stations
  if (activeLevel.value === 'town') {
    selectedStations.value = children.value.filter(c => c.type === 'station');
  } else {
    selectedStations.value = [];
  }
}

function selectTop() {
  activeLevel.value = null;
  activeId.value = null;
  drillStack.value = [];
  fetchDrillChildren();
}

async function drill(child: { id: number | string; name: string; type: string; stations_count?: number }) {
  if (child.type === 'station') {
    // when station clicked, open the edit modal instead of navigating
    await openEditModal(child.id);
    return;
  }

  // push current drill location so we can go back one level
  drillStack.value.push({ level: activeLevel.value, id: activeId.value });

  if (child.type === 'electoral_district') {
    activeLevel.value = 'electoral_district';
    activeId.value = child.id;
  } else if (child.type === 'district') {
    activeLevel.value = 'district';
    activeId.value = child.id;
  } else if (child.type === 'town') {
    activeLevel.value = 'town';
    activeId.value = child.id;
  }
  await fetchDrillChildren();
}

function drillUp() {
  // pop previous state and navigate to it
  if (!drillStack.value.length) {
    // if nothing in stack, reset to top
    selectTop();
    return;
  }

  const prev = drillStack.value.pop();
  activeLevel.value = (prev as any)?.level ?? null;
  activeId.value = (prev as any)?.id ?? null;
  fetchDrillChildren();
}

// edit modal state (fetches the controller edit payload via Inertia JSON)
const editModalOpen = ref(false);
const editModalData = ref({ station: null as any, towns: [] as any[], elections: [] as any[] });

async function openEditModal(id: number | string) {
  // Fetch a JSON-only payload for the modal to avoid Inertia response
  // negotiation. This endpoint returns the station, towns and elections.
  try {
    const res = await fetch(`/admin/local-polling-stations/${id}/payload`, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!res.ok) {
      // Fall back to full navigation if payload endpoint isn't available.
      router.visit(`/admin/local-polling-stations/${id}/edit`);
      return;
    }

    const payload = await res.json();

    editModalData.value.station = payload.station ?? null;
    editModalData.value.towns = payload.towns ?? [];
    editModalData.value.elections = payload.elections ?? [];
    editModalOpen.value = true;
  } catch (e) {
    console.error('Failed to open edit modal, falling back to full page navigation', e);
    router.visit(`/admin/local-polling-stations/${id}/edit`);
  }
}

function onModalUpdated(updated: any) {
  if (!updated || !updated.id) return;
  const idx = allStations.value.findIndex((s: any) => s.id === updated.id);
  if (idx !== -1) allStations.value[idx] = { ...allStations.value[idx], ...updated };
}
</script>

<template>

  <Head title="Local Polling Stations" />

  <AdminLayout>
    <div class="space-y-6">
      <div class="flex gap-6">
        <aside class="w-72 bg-background/5 p-4 rounded-lg">
            <div class="space-y-2">
              <div class="flex items-center gap-2">
                
                <button class="flex-1 text-right font-bold px-3 py-2 rounded-md hover:bg-gray-100" :class="{ 'bg-gray-100': !activeLevel }" @click="selectTop()">
                  جميع الدوائر الانتخابية
                </button>
                <button
                  v-if="drillStack.length"
                  @click="drillUp()"
                  class="p-2 rounded-md hover:bg-gray-100 text-gray-600"
                  title="Back one level"
                >
                  <ChevronLeft class="size-4" />
                </button>
              </div>

            <transition-group name="list" tag="div">
                <template v-if="children.length">
                <span class="block text-sm font-bold text-gray-400 mb-2">
                  {{
                  children[0].type === 'electoral_district'
                    ? 'الدائرة الإنتخابية'
                    : children[0].type === 'district'
                    ? 'القضاء'
                    : children[0].type === 'town'
                      ? 'القرى والبلدات'
                      : children[0].type === 'station'
                      ? 'مراكز الإقتراع'
                      : children[0].type
                  }}
                </span>
                </template>
              <div v-for="child in children" :key="child.type + child.id"
                class="flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-100 cursor-pointer"
                @click="drill(child)">

                  <div class="text-sm font-medium">{{ child.name }}</div>
                <span v-if="child.stations_count !== undefined" class="ml-2 text-xs text-gray-700">
                  ({{ child.stations_count }})
                </span>
              </div>
            </transition-group>
          </div>
        </aside>
        <div class="flex-1">
          <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-900">Local Polling Stations</h1>
            <Button as-child>
              <Link href="/admin/local-polling-stations/create">New Station</Link>
            </Button>
          </div>

          <div class="space-y-4">
            <div v-if="activeLevel === 'town'">
              <h3 class="font-semibold">Stations in selected town</h3>
              <ul class="space-y-2 mt-2">
                <li v-for="s in selectedStations" :key="s.id"
                  class="flex items-center justify-between p-3 bg-background rounded shadow-sm cursor-pointer"
                  role="button"
                  tabindex="0"
                  @click.prevent="openEditModal(s.id)"
                  @keydown.enter.prevent="openEditModal(s.id)">
                  <div>
                    <div class="font-medium">{{ s.name }}</div>
                    <div class="text-xs text-gray-500">{{ s.type }}</div>
                  </div>
                  <div class="flex gap-3">
                      <button @click.prevent.stop="openEditModal(s.id)" class="text-blue-600">View</button>
                    </div>
                </li>
              </ul>
            </div>

            <div v-else class="p-8 bg-background rounded-lg border-dashed border-2 border-gray-100 text-center">
              <div class="max-w-xl mx-auto text-gray-600">
                <h3 class="text-lg font-semibold mb-2">Select a town to view stations</h3>
                <p class="text-sm">Use the navigation on the left to drill down into electoral districts, districts and towns. When you select a town you'll see its polling stations here.</p>
                <div class="mt-4">
                  <Button as-child>
                    <Link href="/admin/local-polling-stations/create">New Station</Link>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
  <LocalPollingStationEditModal v-model="editModalOpen" :station="editModalData.station" :towns="editModalData.towns" :elections="editModalData.elections" @updated="onModalUpdated" />
</template>