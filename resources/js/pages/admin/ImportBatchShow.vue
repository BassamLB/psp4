<template>
  <AdminLayout>
    <Head title="Import Batch" />

    <div class="max-w-4xl mx-auto p-6">
      <h1 class="text-2xl mb-4">Import Batch #{{ batch.id }}</h1>

      <div class="mb-4 p-4 bg-card rounded">
        <div><strong>Upload:</strong> {{ batch.voter_upload_id }}</div>
        <div><strong>Status:</strong> {{ batch.status }}</div>
        <div><strong>Options:</strong> <pre class="whitespace-pre-wrap">{{ JSON.stringify(batch.options || {}, null, 2) }}</pre></div>
        <div v-if="report">
          <strong>Report:</strong>
          <pre class="whitespace-pre-wrap">{{ JSON.stringify(report, null, 2) }}</pre>
        </div>
      </div>

      <div class="mb-6">
        <form :action="`/admin/import-batches/${batch.id}/soft-delete`" method="post">
          <input type="hidden" name="_token" :value="csrf" />
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2">
              <input type="checkbox" name="apply" value="1" />
              <span class="text-sm">تطبيق الحذف الفعلي (غير محدد = معرض)</span>
            </label>
            <Button type="submit" variant="destructive">تشغيل فحص الحذف / تطبيق</Button>
            <a :href="`/admin/import-voters`" class="ml-auto text-sm text-muted-foreground">العودة</a>
          </div>
        </form>
      </div>

      <div>
        <h2 class="text-lg mb-2">صفوف التصدير (أول 1000)</h2>
        <div v-if="rows.length === 0" class="text-sm text-muted-foreground">لا توجد صفوف مؤقتة.</div>
        <table v-else class="w-full text-sm border-collapse">
          <thead>
            <tr class="text-right">
              <th class="pb-2">ID</th>
              <th class="pb-2">Sijil</th>
              <th class="pb-2">Town</th>
              <th class="pb-2">Name</th>
              <th class="pb-2">Processed</th>
              <th class="pb-2">Notes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" class="border-t">
              <td class="py-2">{{ r.id }}</td>
              <td class="py-2">{{ r.sijil_number }}</td>
              <td class="py-2">{{ r.town_id }}</td>
              <td class="py-2">{{ r.name }}</td>
              <td class="py-2">{{ r.processed ? 'yes' : 'no' }}</td>
              <td class="py-2"><pre class="whitespace-pre-wrap">{{ JSON.stringify(r.notes || {}, null, 2) }}</pre></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { usePage, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';

const page = usePage();
const props = (page.props.value ?? page.props) as any;

const batch = props.batch ?? {};
const rows = props.temp_rows ?? [];
const report = props.report ?? null;
const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
</script>
