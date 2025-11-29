<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({ cities: Array, districts: Array });

const form = useForm({
  number: '',
  city_id: props.cities.length ? props.cities[0].id : null,
  mission: '',
  center_name: '',
  center_address: '',
  from_id_number: null,
  to_id_number: null,
  electoral_districts: [],
});

function submit() {
  form.post('/admin/polling-stations');
}
</script>

<template>
  <div>
    <h1 class="text-xl font-semibold mb-4">Create Polling Station</h1>
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
        <button type="submit" class="btn">Save</button>
        <a href="/admin/polling-stations" class="btn">Cancel</a>
      </div>
    </form>
  </div>
</template>
