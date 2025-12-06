<script setup lang="ts">
import { Link, router, Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogClose } from '@/components/ui/dialog';
import { Search, Edit3, Trash2, PlusCircle, Users, ChevronLeft, ChevronRight, AlertTriangle } from 'lucide-vue-next';

const props = defineProps<{ roles: { data: Array<{ id: number; name: string }>; next_page_url?: string | null; prev_page_url?: string | null; current_page?: number; last_page?: number; [key: string]: any } }>();

const showConfirm = ref(false);
const deletingId = ref<number | null>(null);
const query = ref('');
const showCreate = ref(false);
const showEdit = ref(false);
const editItem = ref<any | null>(null);

const createForm = useForm({ name: '' });
const editForm = useForm({ name: '' });

function confirmDelete(id: number) {
  deletingId.value = id;
  showConfirm.value = true;
}

function cancelDelete() {
  deletingId.value = null;
  showConfirm.value = false;
}

function performDelete() {
  if (deletingId.value === null) return;
  router.delete(`/admin/settings/roles/${deletingId.value}`);
  cancelDelete();
}

function openEdit(item: any) {
  editItem.value = item;
  editForm.reset();
  editForm.name = item.name;
  showEdit.value = true;
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('create')) {
    showCreate.value = true;
  }
  const editId = params.get('edit');
  if (editId) {
    const found = (props.roles?.data ?? []).find((i: any) => String(i.id) === String(editId));
    if (found) {
      editItem.value = found;
      editForm.reset();
      editForm.name = found.name;
      showEdit.value = true;
    }
  }
});

async function submitCreate() {
  await createForm.post('/admin/settings/roles', {
    onSuccess: () => {
      showCreate.value = false;
      createForm.reset();
    },
  });
}

async function submitEdit() {
  if (!editItem.value) return;
  await editForm.put(`/admin/settings/roles/${editItem.value.id}`, {
    onSuccess: () => {
      showEdit.value = false;
      editItem.value = null;
    },
  });
}

const hasItems = computed(() => (props.roles?.data ?? []).length > 0);

const filtered = computed(() => {
  const list = props.roles?.data ?? [];
  const q = String(query.value || '').trim().toLowerCase();
  if (!q) return list;
  return list.filter((i) => String(i.name).toLowerCase().includes(q) || String(i.id).includes(q));
});
</script>

<template>
<Head title="الأدوار" />
  <AdminLayout>
    <div class="space-y-4 p-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="bg-accent-gradient rounded-full p-2 text-white shadow-md flex items-center justify-center">
            <Users class="w-6 h-6" />
          </div>
          <div>
            <h1 class="text-lg font-semibold leading-tight">الأدوار</h1>
            <p class="text-xs text-slate-500">إدارة الأدوار — أضف أو حرّر أو احذف الأدوار.</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input v-model="query" placeholder="بحث..." aria-label="بحث" class="pl-9 pr-3 h-9 rounded-lg border border-slate-200 bg-background text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:text-white" />
          </div>

          <button @click="showCreate = true" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-2 rounded shadow">
            <PlusCircle class="w-4 h-4" />
            جديد
          </button>
        </div>

      </div>

      <div v-if="!hasItems" class="p-6 bg-background rounded text-center text-sm text-gray-600 dark:text-gray-400">لا توجد عناصر بعد. انقر "جديد" لإضافة دور.</div>

      <transition-group appear name="settle" tag="ul" v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <li v-for="(role, index) in filtered" :key="role.id" :style="{ transitionDelay: (index * 45) + 'ms' }" class="settle-item bg-background rounded-lg p-4 shadow hover:shadow-md transform hover:-translate-y-0.5 transition-all duration-150 border border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-background text-slate-700 dark:text-slate-200 font-semibold">{{ role.name.charAt(0).toUpperCase() }}</div>
            <div class="text-right">
              <div class="font-medium text-slate-700 dark:text-white">{{ role.name }}</div>
              <div class="text-xs text-slate-400 dark:text-slate-300">معرّف #{{ role.id }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openEdit(role)" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-600/20 px-2 py-1 rounded"><Edit3 class="w-4 h-4" /><span class="text-sm">تعديل</span></button>
            <button @click="confirmDelete(role.id)" class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-600/20 px-2 py-1 rounded"><Trash2 class="w-4 h-4" /><span class="text-sm">حذف</span></button>
          </div>
        </li>
      </transition-group>

      <nav v-if="props.roles.prev_page_url || props.roles.next_page_url" class="flex items-center justify-center gap-4 mt-4">
        <Link v-if="props.roles.prev_page_url" :href="props.roles.prev_page_url" class="px-3 py-1 bg-background border rounded hover:bg-gray-50 inline-flex items-center gap-2 dark:text-gray-300 dark:hover:bg-gray-600 dark:border-slate-700"><ChevronLeft class="w-4 h-4" />السابق</Link>
        <span class="text-sm text-gray-500">صفحة {{ props.roles.current_page || 1 }} من {{ props.roles.last_page || 1 }}</span>
        <Link v-if="props.roles.next_page_url" :href="props.roles.next_page_url" class="px-3 py-1 bg-background border rounded hover:bg-gray-50 inline-flex items-center gap-2 dark:text-gray-300 dark:hover:bg-gray-600 dark:border-slate-700">التالي<ChevronRight class="w-4 h-4" /></Link>
      </nav>

      <Dialog :open="showConfirm" @update:open="showConfirm = $event">
        <DialogContent class="sm:max-w-md bg-background rounded-xl shadow-xl w-11/12 max-w-md p-6 text-right border border-slate-100 dark:border-slate-700">
          <div class="flex items-start gap-3">
            <div class="flex-none rounded-full bg-red-100 p-3 text-red-600"><AlertTriangle class="w-6 h-6" /></div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold mb-1">تأكيد الحذف</h3>
              <p class="text-sm text-slate-500">هل أنت متأكد أنك تريد حذف هذا العنصر؟ هذا الإجراء لا يمكن التراجع عنه.</p>
            </div>
          </div>
          <div class="mt-6 flex justify-between">
            <button @click="cancelDelete" class="px-4 py-2 bg-background rounded-lg text-sm text-slate-700 hover:bg-gray-200 dark:text-white dark:hover:bg-gray-600">إلغاء</button>
            <button @click="performDelete" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">تأكيد الحذف</button>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Create Dialog -->
      <Dialog :open="showCreate" @update:open="showCreate = $event">
        <DialogContent class="sm:max-w-md bg-background rounded-xl shadow-xl w-11/12 max-w-md p-6 text-right border border-slate-100 dark:border-slate-700">
          <DialogHeader class="flex items-center justify-between">
            <DialogTitle>إنشاء دور</DialogTitle>
            <DialogClose />
          </DialogHeader>
          <DialogDescription>
            أضف دوراً جديداً إلى النظام.
          </DialogDescription>

          <div class="mt-4">
            <div>
              <label class="block text-sm font-medium mb-1">الاسم</label>
              <input v-model="createForm.name" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600  dark:text-white" />
              <div v-if="createForm.errors.name" class="text-xs text-red-600 mt-1">{{ createForm.errors.name }}</div>
            </div>

            <div class="mt-6 flex justify-between">
              <button @click="showCreate = false" class="px-4 py-2 border rounded text-sm text-slate-700 bg-background hover:bg-gray-200  dark:text-white dark:hover:bg-gray-600 border-slate-200 dark:border-slate-700">إلغاء</button>
              <button @click.prevent="submitCreate" :disabled="createForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded text-sm">{{ createForm.processing ? 'جاري الحفظ…' : 'حفظ' }}</button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Edit Dialog -->
      <Dialog :open="showEdit" @update:open="showEdit = $event">
        <DialogContent class="sm:max-w-md bg-background rounded-xl shadow-xl w-11/12 max-w-md p-6 text-right border border-slate-100 dark:border-slate-700">
          <DialogHeader class="flex items-center justify-between">
            <DialogTitle>تعديل دور</DialogTitle>
            <DialogClose />
          </DialogHeader>
          <DialogDescription>
            حرّر الدور المُختار.
          </DialogDescription>

          <div class="mt-4">
            <div>
              <label class="block text-sm font-medium mb-1">الاسم</label>
              <input v-model="editForm.name" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:text-white" />
              <div v-if="editForm.errors.name" class="text-xs text-red-600 mt-1">{{ editForm.errors.name }}</div>
            </div>

            <div class="mt-6 flex justify-between">
              <button @click="showEdit = false" class="px-4 py-2 border rounded text-sm text-slate-700 bg-background hover:bg-gray-200  dark:text-white dark:hover:bg-gray-600 border-slate-200 dark:border-slate-700">إلغاء</button>
              <button @click.prevent="submitEdit" :disabled="editForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded text-sm">{{ editForm.processing ? 'جاري الحفظ…' : 'حفظ' }}</button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

    </div>
  </AdminLayout>
</template>
