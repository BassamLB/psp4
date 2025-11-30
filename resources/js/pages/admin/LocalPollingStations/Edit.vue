<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';

interface Station {
  id: number;
  election_id?: number | null;
  town_id?: number | null;
  station_number?: number | null;
  location?: string;
  registered_voters?: number | null;
  white_papers_count?: number | null;
  cancelled_papers_count?: number | null;
  voters_count?: number | null;
  is_open?: boolean;
  is_on_hold?: boolean;
  is_closed?: boolean;
  is_done?: boolean;
  is_checked?: boolean;
  is_final?: boolean;
}

const props = defineProps<{ station: Station; towns: Array<any>; elections: Array<any> }>();

const form = useForm({
  election_id: props.station.election_id ?? (props.elections.length ? props.elections[0].id : null),
  town_id: props.station.town_id ?? (props.towns.length ? props.towns[0].id : null),
  station_number: props.station.station_number ?? null,
  location: props.station.location ?? '',
  registered_voters: props.station.registered_voters ?? null,
  white_papers_count: props.station.white_papers_count ?? null,
  cancelled_papers_count: props.station.cancelled_papers_count ?? null,
  voters_count: props.station.voters_count ?? null,
  is_open: props.station.is_open ?? false,
  is_on_hold: props.station.is_on_hold ?? false,
  is_closed: props.station.is_closed ?? false,
  is_done: props.station.is_done ?? false,
  is_checked: props.station.is_checked ?? false,
  is_final: props.station.is_final ?? false,
});

function submit() {
  form.patch(`/admin/local-polling-stations/${props.station.id}`);
}
</script>

<template>
  <Head title="Edit Local Polling Station" />
  
  <AdminLayout>
    <div class="max-w-3xl">
      <h1 class="text-2xl font-semibold text-gray-900 mb-6">Edit Local Polling Station</h1>
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
