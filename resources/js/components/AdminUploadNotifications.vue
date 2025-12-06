<template>
  <div v-if="visible" class="fixed right-4 top-4 z-50 flex flex-col gap-3 max-w-xs" aria-live="polite">
    <transition-group name="slide" tag="div">
      <div
        v-for="n in notifications"
        :key="n.id"
        class="bg-white shadow-lg rounded-lg p-2 mb-1 border w-80 text-sm text-right"
      >
        <div class="flex justify-between items-start gap-2">
          <div>
            <div class="font-semibold">
              {{ n.type === 'uploaded' ? 'ملف جديد مُحمّل' : (n.type === 'cleaned' ? 'انتهى تنظيف الملف' : (n.type === 'clean_failed' ? 'فشل تنظيف الملف' : 'تنبيه')) }}
            </div>
            <div class="text-gray-600 text-xs">{{ n.filename }}</div>
          </div>
          <div class="flex items-center gap-2">
            <button @click="dismiss(n.id)" class="text-gray-400 hover:text-gray-700">إغلاق</button>
          </div>
        </div>
        <div class="mt-2 text-xs text-gray-700">
          <div>الحجم: {{ formatBytes(n.size) }}</div>
          <div>الحالة: {{ statusLabel(n.status) }}</div>
          <div class="mt-2 text-xs text-gray-500">{{ new Date(n.created_at).toLocaleString() }}</div>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';

interface UploadNotification {
  id: number | string;
  filename: string;
  path: string;
  size: number;
  status: string;
  created_at: string;
  type?: 'uploaded' | 'cleaned' | 'clean_failed' | string;
}

const STORAGE_KEY = 'admin_upload_notifications_v1';

// Load persisted notifications from sessionStorage so they survive Inertia navigations
function loadPersisted(): Array<UploadNotification> {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as Array<UploadNotification>;
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

const notifications = reactive<Array<UploadNotification>>(loadPersisted());
const visible = ref(true);

function formatBytes(bytes: number) {
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

function dismiss(id: number | string) {
  const index = notifications.findIndex((x) => x.id === id);
  if (index !== -1) notifications.splice(index, 1);
  if (notifications.length === 0) visible.value = false;
}

function statusLabel(status: string) {
  switch ((status || '').toString()) {
    case 'queued':
      return 'قيد الانتظار';
    case 'cleaned':
      return 'منقح';
    case 'clean_failed':
      return 'فشل التنظيف';
    case 'invalid':
      return 'غير صالح';
    default:
      return status ?? '-';
  }
}

// Persist notifications when they change
watch(
  () => notifications.slice(),
  (next) => {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
      // ignore
    }
  },
  { deep: true }
);

// Sync across tabs using storage events
function onStorageEvent(e: StorageEvent) {
  if (e.key !== STORAGE_KEY) return;
  try {
    const next = e.newValue ? JSON.parse(e.newValue) : [];
    // replace content (shallow)
    notifications.splice(0, notifications.length, ...(Array.isArray(next) ? next : []));
    visible.value = notifications.length > 0;
  } catch {
    // ignore
  }
}
window.addEventListener('storage', onStorageEvent);

let channel: any = null;

onMounted(() => {
  // Attempt to subscribe when Echo becomes available. Echo may be initialized
  // asynchronously in the layout, so poll briefly until it's present.
  const trySubscribe = () => {
    if (typeof window === 'undefined' || !(window as any).Echo) return false;

    try {
       
      console.debug('Attempting to subscribe to private channel admin.uploads');
      channel = (window as any).Echo.private('admin.uploads');
      channel.listen('.VoterUploadCreated', (payload: any) => {
        // Insert at the end so first stays at top, last at bottom
        notifications.push({
          id: payload.id,
          filename: payload.filename,
          path: payload.path,
          size: payload.size ?? 0,
          status: payload.status ?? 'queued',
          type: 'uploaded',
          created_at: payload.created_at ?? new Date().toISOString(),
        });
        visible.value = true;
      }).error((err: any) => {
        // Some error callbacks pass undefined; log extra state
         
        console.error('Channel subscription error', err, {
          echo: !!(window as any).Echo,
          connector: (window as any).Echo?.connector ?? null,
          pusher: (window as any).Echo?.connector?.pusher ?? null,
          channel: channel?.subscription ?? null,
        });
      });

      // Also listen for cleaning results so admins receive notifications when a
      // clean completes or fails.
      channel.listen('.VoterUploadCleaned', (payload: any) => {
        const st = payload.status ?? 'cleaned';
        notifications.push({
          id: payload.id ?? `clean-${Date.now()}`,
          filename: payload.filename ?? 'unknown',
          path: payload.path ?? '',
          size: payload.size ?? 0,
          status: st,
          type: st === 'cleaned' ? 'cleaned' : 'clean_failed',
          created_at: payload.created_at ?? new Date().toISOString(),
        });
        visible.value = true;
      });

      // Bind to low-level pusher events to capture subscription/connection failures
      try {
        const pusher = (window as any).Echo?.connector?.pusher;
        if (pusher) {
          pusher.bind('pusher:subscription_error', (data: any) => {
             
            console.error('pusher:subscription_error', data);
          });

          pusher.bind('pusher:subscription_succeeded', (data: any) => {
             
            console.info('pusher:subscription_succeeded', data);
          });

          pusher.bind('pusher:connection_error', (err: any) => {
             
            console.error('pusher:connection_error', err);
          });
        }
      } catch {
        // ignore
      }

       
      console.info('Subscribed to admin.uploads');
      return true;
    } catch (e) {
       
      console.warn('Echo private channel subscription failed', e);
      return false;
    }
  };

  if (!trySubscribe()) {
    const interval = setInterval(() => {
      if (trySubscribe()) {
        clearInterval(interval);
      }
    }, 250);

    // stop trying after 10s
    setTimeout(() => clearInterval(interval), 10000);
  }
});

onBeforeUnmount(() => {
  try {
    if (channel) {
      channel.stopListening('.VoterUploadCreated');
      channel.stopListening('.VoterUploadCleaned');
    }
  } catch {
    // ignore
  }
  try { window.removeEventListener('storage', onStorageEvent); } catch {}
});
</script>

<style scoped>
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}
</style>
