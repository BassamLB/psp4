<template>
  <AdminLayout>
    <Head title="استيراد الناخبين - الإدارة" />

    <div class="container mx-auto p-6 max-w-7xl space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">استيراد الناخبين</h1>
          <p class="text-muted-foreground mt-1">رفع و معالجة ملفات CSV للناخبين - لكل قضاء على حدا</p>
          <img src="/images/votersHeadings3.png" alt="Import Voters Excel Headings" class="mt-2 w-full" />
        </div>
      </div>

      <!-- Notice Alert -->
      <Alert v-if="notice" variant="default" class="bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-900">
        <AlertCircle class="h-4 w-4 text-blue-600 dark:text-blue-400" />
        <AlertTitle class="text-blue-900 dark:text-blue-100">إشعار</AlertTitle>
        <AlertDescription class="text-blue-800 dark:text-blue-200">{{ notice }}</AlertDescription>
      </Alert>

      <!-- Upload Card -->
      <Card>
        <CardHeader>
          <CardTitle>رفع ملف جديد</CardTitle>
          <CardDescription>اختر ملف CSV للناخبين لبدء عملية الاستيراد</CardDescription>
        </CardHeader>
        <CardContent>
          <form action="/admin/import-voters" method="post" enctype="multipart/form-data" class="space-y-4" @submit="refreshCsrfToken">
            <input type="hidden" name="_token" ref="csrfTokenInput" :value="csrf" />

            <div class="space-y-2">
              <Label for="file-upload">ملف CSV</Label>
              <div class="flex gap-3 items-center">
                <Input 
                  id="file-upload"
                  type="file" 
                  name="file" 
                  accept=".csv" 
                  required 
                  class="cursor-pointer"
                />
                <Button type="submit" class="whitespace-nowrap">
                  <Upload class="mr-2 h-4 w-4" />
                  رفع الملف
                </Button>
              </div>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Flash Output Alert -->
      <Alert v-if="flashOutput" variant="default" class="bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-900">
        <FileCheck class="h-4 w-4 text-green-600 dark:text-green-400" />
        <AlertTitle class="text-green-900 dark:text-green-100">نتيجة العملية</AlertTitle>
        <AlertDescription class="text-green-800 dark:text-green-200">
          <pre class="mt-2 whitespace-pre-wrap text-sm">{{ flashOutput }}</pre>
          <div v-if="invalidRows" class="mt-3">
            <p class="font-medium mb-2">أخطاء في السطور ({{ invalidCount }})</p>
            <Button as="a" size="sm" variant="outline" :href="`/admin/import-voters/download/${encodeURIComponent(invalidRows)}`">
              <Download class="mr-2 h-4 w-4" />
              تحميل سطور الأخطاء
            </Button>
          </div>
        </AlertDescription>
      </Alert>

      <!-- Uploads Table -->
      <Card>
        <CardHeader>
          <CardTitle>الملفات المرفوعة</CardTitle>
          <CardDescription>قائمة بجميع الملفات المرفوعة وحالتها</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="uploads.length === 0" class="text-center py-12 text-muted-foreground">
            <FileX class="mx-auto h-12 w-12 mb-4 opacity-50" />
            <p>لا توجد ملفات محفوظة</p>
          </div>
          <div v-else class="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow class="border-b hover:bg-transparent">
                  <TableHead class="text-right font-medium">الملف</TableHead>
                  <TableHead class="text-right font-medium">الحجم</TableHead>
                  <TableHead class="text-right font-medium">تاريخ الرفع</TableHead>
                  <TableHead class="text-right font-medium">الحالة</TableHead>
                  <TableHead class="text-right w-[300px] font-medium">الإجراءات</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-for="f in uploads" :key="f.id ?? f.path">
                  <!-- Main File Row -->
                  <TableRow>
                    <TableCell class="font-medium">{{ f.original_name }}</TableCell>
                    <TableCell>{{ formatBytes(f.size) }}</TableCell>
                    <TableCell>{{ formatDate(f.created_at) }}</TableCell>
                    <TableCell>
                      <Badge :variant="getStatusVariant(f.status)" class="font-normal">
                        {{ getStatusLabel(f.status) }}
                      </Badge>
                      <!-- Progress Bar -->
                      <div v-if="f.meta?.progress?.percent !== undefined" class="mt-2">
                        <div class="w-full bg-muted rounded-full h-2 overflow-hidden">
                          <div class="bg-primary h-2 transition-all duration-300" :style="{ width: `${f.meta.progress.percent}%` }"></div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                          {{ f.meta.progress.percent }}%
                          <span v-if="f.meta.progress.processed">({{ f.meta.progress.processed }} / {{ f.meta.progress.total ?? '?' }})</span>
                        </p>
                      </div>
                    </TableCell>
                  <TableCell>
                    <div class="flex flex-wrap gap-1">
                      <!-- Download Original -->
                      <Button as="a" size="sm" variant="outline" :href="`/admin/import-voters/download/${encodeURIComponent(f.name)}`">
                        <Download class="h-3 w-3" />
                      </Button>

                      <!-- Clean Button -->
                      <Button v-if="!f.meta?.cleaned_path" size="sm" variant="secondary" @click="cleanFile(f.id)">
                        <Sparkles class="h-3 w-3" />
                      </Button>

                      <!-- Download Cleaned -->
                      <Button v-if="f.meta?.cleaned_path" as="a" size="sm" variant="outline" :href="`/admin/import-voters/download/${encodeURIComponent((f.meta.cleaned_path || '').split('/').pop())}`">
                        <FileCheck class="h-3 w-3" />
                      </Button>

                      <!-- Download Invalid -->
                      <Button v-if="f.meta?.invalid_path" as="a" size="sm" variant="outline" :href="`/admin/import-voters/download/${encodeURIComponent((f.meta.invalid_path || '').split('/').pop())}`">
                        <FileX class="h-3 w-3" />
                      </Button>

                      <!-- View Report -->
                      <Button v-if="f.meta?.clean_output" as="a" size="sm" variant="ghost" :href="`/admin/import-voters/${encodeURIComponent(f.id)}/report`" target="_blank">
                        <FileText class="h-3 w-3" />
                      </Button>

                      <!-- View Batch -->
                      <Button v-if="f.meta?.import_batch_id" size="sm" variant="ghost" @click="viewBatch(f.meta.import_batch_id)">
                        <PackageOpen class="h-3 w-3" />
                      </Button>

                      <!-- View Log -->
                      <Button v-if="f.meta?.log" as="a" size="sm" variant="ghost" :href="`/storage/logs/${(f.meta.log || '').split('/').pop()}`" target="_blank">
                        <ScrollText class="h-3 w-3" />
                      </Button>

                      <!-- Import to DB Dialog -->
                      <Dialog v-if="f.meta?.cleaned_path || f.cleaned_file">
                        <DialogTrigger as-child>
                          <Button size="sm">
                            <Database class="h-3 w-3 mr-1" />
                            استيراد
                          </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-md">
                          <form :action="`/admin/import-voters/${encodeURIComponent(f.id)}/import`" method="post" class="space-y-6">
                            <input type="hidden" name="_token" :value="csrf" />
                            <DialogHeader>
                              <DialogTitle>استيراد إلى قاعدة البيانات</DialogTitle>
                              <DialogDescription>
                                اختر خيارات الاستيراد لملف {{ f.original_name }}
                              </DialogDescription>
                            </DialogHeader>

                            <div class="space-y-4">
                              <div class="space-y-2">
                                <Label for="batch-size">حجم الدُفعة</Label>
                                <Input 
                                  id="batch-size" 
                                  type="number" 
                                  name="batch" 
                                  value="1000" 
                                  min="100" 
                                  max="10000"
                                />
                              </div>

                              <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                  <Checkbox id="skip-header" name="skip_header" />
                                  <Label for="skip-header" class="cursor-pointer">تخطى السطر الأول (العنوان)</Label>
                                </div>
                                <div class="flex items-center gap-2">
                                  <Checkbox id="dry-run" name="dry_run" />
                                  <Label for="dry-run" class="cursor-pointer">تجربة فقط (بدون حفظ)</Label>
                                </div>
                                <div class="flex items-center gap-2">
                                  <Checkbox id="show-unmatched" name="show_unmatched" />
                                  <Label for="show-unmatched" class="cursor-pointer">إظهار غير المتطابق</Label>
                                </div>
                              </div>
                            </div>

                            <DialogFooter class="gap-2">
                              <DialogClose as-child>
                                <Button type="button" variant="outline">إلغاء</Button>
                              </DialogClose>
                              <Button type="submit" class="bg-primary text-primary-foreground hover:bg-primary/90">ابدأ الاستيراد</Button>
                            </DialogFooter>
                          </form>
                        </DialogContent>
                      </Dialog>

                      <!-- Delete Button -->
                      <Button size="sm" variant="destructive" @click="deleteFile(f.name)">
                        <Trash2 class="h-3 w-3" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Batch Modal -->
    <Dialog v-model:open="showBatchModal">
      <DialogContent class="sm:max-w-4xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>معلومات الدُفعة</DialogTitle>
          <DialogDescription>تفاصيل دفعة الاستيراد والصفوف المؤقتة</DialogDescription>
        </DialogHeader>

        <div v-if="batchData" class="space-y-4">
          <!-- Batch Info -->
          <div class="rounded-lg border p-4 bg-muted/30">
            <h3 class="font-semibold mb-2">معلومات الدُفعة</h3>
            <pre class="text-xs whitespace-pre-wrap overflow-auto max-h-48 font-mono">{{ JSON.stringify(batchData.batch || {}, null, 2) }}</pre>
          </div>

          <!-- Report -->
          <div class="rounded-lg border p-4 bg-muted/30">
            <h3 class="font-semibold mb-2">التقرير</h3>
            <pre class="text-xs whitespace-pre-wrap overflow-auto max-h-48 font-mono">{{ JSON.stringify(batchData.report || {}, null, 2) }}</pre>
          </div>

          <!-- Temp Rows -->
          <div v-if="batchData.temp_rows?.length" class="rounded-lg border p-4 bg-muted/30">
            <h3 class="font-semibold mb-3">الصفوف المؤقتة ({{ batchData.temp_rows.length }} صفوف)</h3>
            <div class="rounded-md border overflow-auto max-h-64">
              <Table>
                <TableHeader>
                  <TableRow class="border-b hover:bg-transparent">
                    <TableHead>رقم السجل</TableHead>
                    <TableHead>البلدة</TableHead>
                    <TableHead>الاسم</TableHead>
                    <TableHead>ملاحظات</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="r in batchData.temp_rows.slice(0, 100)" :key="r.id">
                    <TableCell>{{ r.sijil_number }}</TableCell>
                    <TableCell>{{ r.town_id }}</TableCell>
                    <TableCell class="text-xs">{{ r.name }}</TableCell>
                    <TableCell>
                      <pre v-if="r.notes" class="text-xs whitespace-pre-wrap text-muted-foreground font-mono">{{ JSON.stringify(r.notes, null, 2) }}</pre>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4 items-center">
            <form v-if="batchData.batch?.id" :action="`/admin/import-batches/${encodeURIComponent(batchData.batch.id)}/soft-delete`" method="post" class="flex gap-3 items-center">
              <input type="hidden" name="_token" :value="csrf" />
              <div class="flex items-center gap-2">
                <Checkbox id="apply-delete" name="apply" value="1" />
                <Label for="apply-delete" class="cursor-pointer">تطبيق الحذف الفعلي</Label>
              </div>
              <Button type="submit" variant="destructive" size="sm">حذف</Button>
            </form>
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button variant="outline" @click="showBatchModal = false">إغلاق</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

<script setup lang="ts">
import { usePage, Head } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
  DialogDescription,
  DialogClose,
} from '@/components/ui/dialog';
import { 
  Upload, Download, FileCheck, FileX, FileText, Database, 
  Trash2, AlertCircle, Sparkles, PackageOpen, ScrollText 
} from 'lucide-vue-next';

const page = usePage();
const csrf = ref(document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '');
const csrfTokenInput = ref<HTMLInputElement | null>(null);

// Helper functions for status display
function getStatusVariant(status: string | undefined): 'default' | 'secondary' | 'destructive' | 'outline' {
  const s = status?.toLowerCase() || 'queued';
  if (s.includes('error') || s.includes('failed')) return 'destructive';
  if (s.includes('completed') || s.includes('imported') || s.includes('merged')) return 'default';
  if (s.includes('cleaning') || s.includes('importing') || s.includes('merging')) return 'secondary';
  return 'outline';
}

function getStatusLabel(status: string | undefined): string {
  const statusMap: Record<string, string> = {
    'queued': 'في الانتظار',
    'pending': 'قيد التعليق',
    'cleaning': 'جاري التنظيف',
    'cleaned': 'تم التنظيف',
    'importing-temp': 'جاري الاستيراد المؤقت',
    'temp_imported': 'تم الاستيراد المؤقت',
    'merging': 'جاري الدمج',
    'merged': 'تم الدمج',
    'completed': 'مكتمل',
    'error': 'خطأ',
    'failed': 'فشل'
  };
  return statusMap[status?.toLowerCase() || 'queued'] || status || 'غير معروف';
}

// Refresh CSRF token before form submission
function refreshCsrfToken() {
  const freshToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
  if (freshToken && csrf.value !== freshToken) {
    csrf.value = freshToken;
    if (csrfTokenInput.value) {
      csrfTokenInput.value.value = freshToken;
    }
  }
}

// Periodically refresh CSRF token from meta tag (every 5 minutes)
let tokenRefreshInterval: number | undefined;
onMounted(() => {
  tokenRefreshInterval = window.setInterval(() => {
    const freshToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
    if (freshToken && csrf.value !== freshToken) {
      csrf.value = freshToken;
    }
  }, 5 * 60 * 1000); // 5 minutes
});

onBeforeUnmount(() => {
  if (tokenRefreshInterval) {
    clearInterval(tokenRefreshInterval);
  }
});

// page.props can be a reactive object or a ref depending on Inertia helpers/setup.
// Use a computed accessor that handles both shapes and avoids reading undefined.flash
const flashOutput = computed(() => {
  const props = page.props || {};
  const p = props.value ?? props;
  const anyP = p as any;

  const uploaded = anyP?.flash?.uploaded_file ?? anyP?.uploaded_file ?? null;
  const msg = anyP?.flash?.import_output ?? anyP?.flash?.import_message ?? anyP?.import_output ?? anyP?.import_message ?? null;

  if (uploaded && msg) {
    return `Stored file: ${uploaded}\n\n${msg}`;
  }

  if (uploaded) {
    return `Stored file: ${uploaded}`;
  }

  return msg ?? null;
});

const invalidRows = computed(() => {
  const props = page.props || {};
  const p = props.value ?? props;
  const anyP = p as any;

  return anyP?.flash?.invalid_rows ?? anyP?.invalid_rows ?? null;
});

const invalidCount = computed(() => {
  const props = page.props || {};
  const p = props.value ?? props;
  const anyP = p as any;

  return anyP?.flash?.invalid_count ?? anyP?.invalid_count ?? 0;
});

// list of stored uploads from the controller (DB-backed)
// Initialize a reactive uploads list from Inertia props so we can update it
// in response to broadcast events without requiring a full page reload.
const storedFiles = computed(() => {
  const props = page.props || {};
  const p = props.value ?? props;
  const anyP = p as any;

  return Array.isArray(anyP?.uploads) ? anyP.uploads : [];
});

const uploads = ref<Array<any>>(storedFiles.value.slice());

// Modal state for viewing an import batch
const showBatchModal = ref(false);
const batchData = ref<any>(null);
const notice = ref<string | null>(null);

// Keep a small helper to find and merge upload updates
function upsertUpload(update: any) {
  const idx = uploads.value.findIndex((u) => String(u.id) === String(update.id));
  if (idx === -1) {
    uploads.value.unshift(update);
  } else {
    uploads.value[idx] = { ...uploads.value[idx], ...update };
  }
}

let echoChannel: any = null;
onMounted(() => {
  const trySubscribe = () => {
    if (typeof window === 'undefined' || !(window as any).Echo) return false;

    try {
      echoChannel = (window as any).Echo.private('admin.uploads');
      echoChannel.listen('.VoterUploadCleaned', (payload: any) => {
        // payload contains id and updated status/meta
        upsertUpload(payload);
        // brief admin notice
        try {
          notice.value = `Upload #${payload.id} updated: ${payload.status || ''}`;
          setTimeout(() => { notice.value = null; }, 6000);
        } catch {}
      });

      echoChannel.listen('.VoterUploadCreated', (payload: any) => {
        upsertUpload(payload);
      });

      // Progress updates for long imports
      echoChannel.listen('.VoterUploadProgress', (payload: any) => {
        try {
          // merge progress into upload meta so UI can render progress bar
          const upd = { id: payload.id, meta: Object.assign({}, (uploads.value.find(u => String(u.id) === String(payload.id)) || {}).meta || {}, { progress: payload }) };
          upsertUpload(upd);
          // also show short notice
          notice.value = `Upload #${payload.id} progress: ${payload.percent ?? payload.processed ?? ''}`;
          setTimeout(() => { notice.value = null; }, 4000);
        } catch {}
      });

      return true;
    } catch {
      // retry
      return false;
    }
  };

  if (!trySubscribe()) {
    const interval = setInterval(() => {
      if (trySubscribe()) clearInterval(interval);
    }, 250);
    setTimeout(() => clearInterval(interval), 10000);
  }
});

onBeforeUnmount(() => {
  try {
    if (echoChannel) {
      echoChannel.stopListening('.VoterUploadCleaned');
      echoChannel.stopListening('.VoterUploadCreated');
    }
  } catch {}
});

async function deleteFile(filename: string) {
  if (! filename) {
    return;
  }

  const ok = confirm(`حذف الملف "${filename}"؟ هذا الإجراء لا يمكن التراجع عنه.`);
  if (! ok) {
    return;
  }

  // Create a form and submit it so Laravel can redirect/back with flash messages
  const form = document.createElement('form');
  form.method = 'post';
  form.action = `/admin/import-voters/${encodeURIComponent(filename)}`;

  const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

  const methodInput = document.createElement('input');
  methodInput.type = 'hidden';
  methodInput.name = '_method';
  methodInput.value = 'DELETE';

  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = '_token';
  csrfInput.value = token;

  form.appendChild(methodInput);
  form.appendChild(csrfInput);
  document.body.appendChild(form);
  form.submit();
}

async function viewBatch(batchId: number | string) {
  if (!batchId) return;
  try {
    const res = await fetch(`/admin/import-batches/${encodeURIComponent(String(batchId))}`, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Failed to load batch');
    const data = await res.json();
    batchData.value = data;
    showBatchModal.value = true;
  } catch (e: any) {
    alert('فشل في تحميل معلومات الدُفعة: ' + (e?.message || String(e)));
  }
}

function cleanFile(uploadId: number | string) {
  if (!uploadId) return;

  const ok = confirm('بدء تنظيف الملف في الخلفية؟');
  if (!ok) return;

  const form = document.createElement('form');
  form.method = 'post';
  form.action = `/admin/import-voters/${encodeURIComponent(String(uploadId))}/clean`;

  const csrfToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = '_token';
  csrfInput.value = csrfToken;

  form.appendChild(csrfInput);
  document.body.appendChild(form);
  form.submit();
}

function formatBytes(bytes: number): string {
  if (!bytes && bytes !== 0) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let val = bytes;
  while (val >= 1024 && i < units.length - 1) {
    val = val / 1024;
    i++;
  }
  return `${val.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
}

function formatDate(value: any): string {
  if (!value) return '-';
  // If it's a numeric timestamp (seconds), convert
  const n = Number(value);
  if (!Number.isNaN(n)) {
    // assume seconds if reasonable
    const asMs = n > 1e12 ? n : n * 1000;
    return new Date(asMs).toLocaleString();
  }

  // fallback
  try {
    return new Date(value).toLocaleString();
  } catch {
    return String(value);
  }
}

// list of stored uploads from the controller (DB-backed)
// (Stored files listing and deletion disabled in this simplified UI.)
</script>
 
