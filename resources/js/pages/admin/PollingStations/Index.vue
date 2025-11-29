<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ stations: Object, cities: Array, districts: Array });

function confirmDelete(id) {
  if (!confirm('Delete this polling station?')) return;
  router.delete(`/admin/polling-stations/${id}`);
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">External Polling Stations</h1>
      <Link href="/admin/polling-stations/create" class="btn">New Station</Link>
    </div>

    <div class="overflow-x-auto bg-white rounded shadow">
      <table class="min-w-full">
        <thead>
          <tr>
            <th class="p-2">#</th>
            <th class="p-2">Center</th>
            <th class="p-2">City</th>
            <th class="p-2">Mission</th>
            <th class="p-2">Districts</th>
            <th class="p-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="station in stations.data" :key="station.id">
            <td class="p-2">{{ station.id }}</td>
            <td class="p-2">{{ station.center_name }}</td>
            <td class="p-2">{{ station.city?.name ?? '—' }}</td>
            <td class="p-2">{{ station.mission ?? '—' }}</td>
            <td class="p-2">{{ station.electoral_districts?.map(d => d.name).join(', ') }}</td>
            <td class="p-2">
              <Link :href="`/admin/polling-stations/${station.id}/edit`" class="text-blue-600 mr-3">Edit</Link>
              <button @click="confirmDelete(station.id)" class="text-red-600">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <nav v-if="stations.links">
        <ul class="flex gap-2">
          <li v-for="link in stations.links" :key="link.label" v-html="link.label" />
        </ul>
      </nav>
    </div>
  </div>
</template>
