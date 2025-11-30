<script setup lang="ts">
import { Link, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { InfiniteScroll } from '@inertiajs/vue3';

defineProps<{
  stations: { data: Array<any>; [key: string]: any };
  cities: Array<any>;
  districts: Array<any>;
}>();

function confirmDelete(id: number) {
  if (!confirm('Delete this polling station?')) return;
  router.delete(`/admin/polling-stations/${id}`);
}
</script>

<template>
  <Head title="External Polling Stations" />
  
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">External Polling Stations</h1>
        <Button as-child>
          <Link href="/admin/polling-stations/create">New Station</Link>
        </Button>
      </div>

      <div class="space-y-4">
        <InfiniteScroll data="stations">
          <div v-for="station in stations.data" :key="station.id"
            class="bg-white rounded-lg border shadow-sm p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div class="flex-1 space-y-2">
                <div class="flex items-center gap-4">
                  <span class="text-lg font-semibold text-gray-900">#{{ station.id }}</span>
                  <span class="text-gray-600">{{ station.center_name }}</span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                  <div>
                    <span class="text-gray-500">City:</span>
                    <span class="ml-2 text-gray-900">{{ station.city?.name ?? '—' }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Mission:</span>
                    <span class="ml-2 text-gray-900">{{ station.mission ?? '—' }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Districts:</span>
                    <span class="ml-2 text-gray-900">{{ station.electoral_districts?.map((d: { name: string }) => d.name).join(', ') ?? '—' }}</span>
                  </div>
                </div>
              </div>
              <div class="flex gap-3 ml-6">
                <Link :href="`/admin/polling-stations/${station.id}/edit`"
                  class="text-blue-600 hover:text-blue-900 font-medium">Edit</Link>
                <button @click="confirmDelete(station.id)" class="text-red-600 hover:text-red-900 font-medium">Delete</button>
              </div>
            </div>
          </div>
        </InfiniteScroll>
      </div>
    </div>
  </AdminLayout>
</template>
