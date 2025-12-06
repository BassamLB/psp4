<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogClose } from '@/components/ui/dialog';
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

const props = defineProps<{
  modelValue: boolean;
  station: Station | null;
  towns: Array<any>;
  elections: Array<any>;
}>();

const emits = defineEmits(['update:modelValue', 'updated']);

const isOpen = ref(!!props.modelValue);

watch(() => props.modelValue, v => isOpen.value = v);

function close() {
  emits('update:modelValue', false);
}

// Build form only when station is provided
const form = useForm<any>({});

watch(() => props.station, (s) => {
  if (!s) return;
  form.reset();
  form.clearErrors();
  form.election_id = s.election_id ?? (props.elections.length ? props.elections[0].id : null);
  form.town_id = s.town_id ?? (props.towns.length ? props.towns[0].id : null);
  form.station_number = s.station_number ?? null;
  form.location = s.location ?? '';
  form.registered_voters = s.registered_voters ?? null;
  form.white_papers_count = s.white_papers_count ?? null;
  form.cancelled_papers_count = s.cancelled_papers_count ?? null;
  form.voters_count = s.voters_count ?? null;
  form.is_open = s.is_open ?? false;
  form.is_on_hold = s.is_on_hold ?? false;
  form.is_closed = s.is_closed ?? false;
  form.is_done = s.is_done ?? false;
  form.is_checked = s.is_checked ?? false;
  form.is_final = s.is_final ?? false;
});

async function submit() {
  if (!props.station) return;
  form.patch(`/admin/local-polling-stations/${props.station.id}`, {
    onSuccess: () => {
      // emit updated station back to parent (we'll pass the current form values for simplicity)
      emits('updated', { ...props.station, ...form });
      close();
    }
  });
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="(v) => { isOpen = v; emits('update:modelValue', v); }">
    <DialogContent class="sm:max-w-2xl bg-background rounded-xl shadow-xl w-11/12 max-w-3xl p-6 text-right border border-slate-100">
      <DialogHeader class="flex items-center justify-between">
        <DialogTitle>تعديل معلومات مركز الإقتراع</DialogTitle>
        <DialogClose />
      </DialogHeader>

      <DialogDescription>
        <form @submit.prevent="submit" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm">البلدة</label>
              <select v-model="form.town_id" class="input w-full">
                <option v-for="t in props.towns" :value="t.id" :key="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm">رقم المركز</label>
              <input v-model="form.station_number" type="number" class="input w-full" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm">الموقع</label>
              <input v-model="form.location" class="input w-full" />
            </div>
            <div>
              <label class="block text-sm">الناخبون المسجلون</label>
              <input v-model="form.registered_voters" type="number" class="input w-full" />
            </div>
            <div>
              <label class="block text-sm">الانتخابات</label>
              <select v-model="form.election_id" class="input w-full">
                <option v-for="e in props.elections" :value="e.id" :key="e.id">{{ e.name }}</option>
              </select>
            </div>
          </div>

          <div class="flex gap-3 justify-end">
            <Button type="submit" :disabled="form.processing">حفظ</Button>
            <Button variant="outline" @click.prevent="close">إلغاء</Button>
          </div>
        </form>
      </DialogDescription>
    </DialogContent>
  </Dialog>
</template>
