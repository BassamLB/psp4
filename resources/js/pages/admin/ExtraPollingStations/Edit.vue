<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';

interface City {
  id: number | string;
  name: string;
}
interface District {
  id: number | string;
  name: string;
}
interface Station {
  id?: number | string;
  number?: string;
  city_id?: number | string;
  mission?: string;
  center_name?: string;
  center_address?: string;
  from_id_number?: number | string | null;
  to_id_number?: number | string | null;
  electoral_districts?: Array<{ id: number | string }>;
}

const props = defineProps<{
  station: Station;
  cities: City[];
  districts: District[];
}>();

const station = props.station ?? {};

const form = useForm({
  number: station.number ?? '',
  city_id: station.city_id ?? (props.cities.length ? props.cities[0].id : null),
  mission: station.mission ?? '',
  center_name: station.center_name ?? '',
  center_address: station.center_address ?? '',
  from_id_number: station.from_id_number ?? null,
  to_id_number: station.to_id_number ?? null,
  electoral_districts: (station.electoral_districts || []).map(d => d.id),
});

function submit() {
  form.patch(`/admin/polling-stations/${props.station.id}`);
}
</script>

<template>
  <Head title="Edit Polling Station" />
  
  <AdminLayout>
    <div class="max-w-3xl">
      <h1 class="text-2xl font-semibold text-gray-900 mb-6">Edit Polling Station</h1>
    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label>Center Name</label>
        <input v-model="form.center_name" class="input" required />
      </div>
      <div>
        <label>City</label>
        <select v-model="form.city_id" class="input">
          <option v-for="city in props.cities" :value="city.id" :key="city.id">{{ city.name }}</option>
        </select>
      </div>
      <div>
        <label>Mission</label>
        <input v-model="form.mission" class="input" />
      </div>
      <div>
        <label>Electoral Districts</label>
        <select v-model="form.electoral_districts" multiple class="input">
          <option v-for="d in props.districts" :value="d.id" :key="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="flex gap-3">
        <Button type="submit" :disabled="form.processing">Update</Button>
        <Button as-child variant="outline">
          <Link href="/admin/polling-stations">Cancel</Link>
        </Button>
      </div>
    </form>
    </div>
  </AdminLayout>
</template>
