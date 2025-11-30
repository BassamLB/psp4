<script setup lang="ts">
import { Link, router, Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{ stations: { data: Array<any>; [key: string]: any }; towns: Array<any>; elections: Array<any> }>();

const allStations = ref([...props.stations.data]);
console.log(allStations);
const isLoading = ref(false);
const hasMore = ref(!!props.stations.next_page_url);

// Drilldown state (electoral districts -> district -> town -> stations)
const children = ref([]);
const activeLevel = ref(null);
const activeId = ref(null);
const selectedStations = ref([]); // when a town is selected we'll populate stations here

function confirmDelete(id) {
  if (!confirm('Delete this polling station?')) return;
  router.delete(`/admin/local-polling-stations/${id}`, {
    onSuccess: () => {
      allStations.value = allStations.value.filter(s => s.id !== id);
    }
  });
}

async function loadMore() {
  if (isLoading.value || !hasMore.value) return;

  isLoading.value = true;

  router.get(props.stations.next_page_url, {}, {
    preserveState: true,
    preserveScroll: true,
    only: ['stations'],
    onSuccess: (page) => {
      const newStations = page.props.stations;
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
  if (activeId.value) params.append('id', activeId.value);

  const res = await fetch(`/admin/results/data?${params.toString()}`);
  if (!res.ok) return;
  const json = await res.json();
  children.value = json.children || [];

  console.log('Drill children:', children.value);

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
  fetchDrillChildren();
}

async function drill(child) {
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
    // when station clicked, navigate to edit/view
    router.get(`/admin/local-polling-stations/${child.id}`);
    return;
  }
  await fetchDrillChildren();
}
</script>

<template>

  <Head title="Local Polling Stations" />

  <AdminLayout>
    <div class="space-y-6">
      <div class="flex gap-6">
        <aside class="w-72 bg-white/5 p-4 rounded-lg">
          <h2 class="text-lg font-semibold mb-2">التنقل</h2>
          <div class="space-y-2">
            <button class="w-full text-left px-3 py-2 rounded-md hover:bg-gray-100" :class="{ 'bg-gray-100': !activeLevel }
              " @click="selectTop()">
              جميع الدوائر الانتخابية
            </button>

            <transition-group name="list" tag="div">
              <div v-for="child in children" :key="child.type + child.id"
                class="flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-100 cursor-pointer"
                @click="drill(child)">

                <div>
                  <div class="text-sm font-medium">{{ child.name }}</div>
                  <div class="text-xs text-gray-500">{{ child.type }}</div>
                </div>
                <div class="text-sm font-mono text-cyan-600">
                  {{ child.stations_count }} stations
                </div>
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
                  class="flex items-center justify-between p-3 bg-white rounded shadow-sm">
                  <div>
                    <div class="font-medium">{{ s.name }}</div>
                    <div class="text-xs text-gray-500">{{ s.type }}</div>
                  </div>
                  <div class="flex gap-3">
                    <Link :href="`/admin/local-polling-stations/${s.id}/edit`" class="text-blue-600">View</Link>
                  </div>
                </li>
              </ul>
            </div>

            <div v-else>
              <div v-for="station in allStations" :key="station.id"
                class="bg-white rounded-lg border shadow-sm p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                  <div class="flex-1 space-y-2">
                    <div class="flex items-center gap-4">
                      <span class="text-lg font-semibold text-gray-900">#{{ station.id }}</span>
                      <span class="text-gray-600">{{ station.town?.name ?? '—' }}</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                      <div>
                        <span class="text-gray-500">Number:</span>
                        <span class="ml-2 text-gray-900">{{ station.station_number ?? '—' }}</span>
                      </div>
                      <div>
                        <span class="text-gray-500">Location:</span>
                        <span class="ml-2 text-gray-900">{{ station.location ?? '—' }}</span>
                      </div>
                      <div>
                        <span class="text-gray-500">Registered:</span>
                        <span class="ml-2 text-gray-900">{{ station.registered_voters ?? '—' }}</span>
                      </div>
                      <div>
                        <span class="text-gray-500">Open:</span>
                        <span class="ml-2 text-gray-900">{{ station.is_open ? 'Yes' : 'No' }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-3 ml-6">
                    <Link :href="`/admin/local-polling-stations/${station.id}/edit`"
                      class="text-blue-600 hover:text-blue-900 font-medium">Edit</Link>
                    <button @click="confirmDelete(station.id)"
                      class="text-red-600 hover:text-red-900 font-medium">Delete</button>
                  </div>
                </div>
              </div>

              <div v-if="isLoading" class="text-center py-4">
                <span class="text-gray-500">Loading more...</span>
              </div>

              <div v-if="!hasMore && allStations.length > 0" class="text-center py-4">
                <span class="text-gray-500">No more stations to load</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>