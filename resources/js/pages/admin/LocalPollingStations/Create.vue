<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';

const props = defineProps({ towns: Array, elections: Array });

const form = useForm({
  election_id: props.elections.length ? props.elections[0].id : null,
  town_id: props.towns.length ? props.towns[0].id : null,
  station_number: null,
  location: '',
  registered_voters: null,
  white_papers_count: null,
  cancelled_papers_count: null,
  voters_count: null,
  is_open: false,
  is_on_hold: false,
  is_closed: false,
  is_done: false,
  is_checked: false,
  is_final: false,
});

function submit() {
  form.post('/admin/local-polling-stations');
}
</script>

<template>
  <Head title="Create Local Polling Station" />
  
  <AdminLayout>
    <div class="max-w-3xl">
      <h1 class="text-2xl font-semibold text-gray-900 mb-6">Create Local Polling Station</h1>
    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label>Town</label>
        <select v-model="form.town_id" class="input">
          <option v-for="t in props.towns" :value="t.id" :key="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div>
        <label>Station Number</label>
        <input v-model="form.station_number" type="number" class="input" />
      </div>
      <div>
        <label>Location</label>
        <input v-model="form.location" class="input" />
      </div>
      <div>
        <label>Registered Voters</label>
        <input v-model="form.registered_voters" type="number" class="input" />
      </div>
      <div class="flex gap-3">
        <Button type="submit" :disabled="form.processing">Save</Button>
        <Button as-child variant="outline">
          <Link href="/admin/local-polling-stations">Cancel</Link>
        </Button>
      </div>
    </form>
    </div>
  </AdminLayout>
</template>
