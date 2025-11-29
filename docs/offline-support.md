# Offline Support Plan — PSP4

Date: 2025-11-27

This document captures the recommended approach, tradeoffs, implementation steps, and example snippets to add offline support to the vote-entry web UI. Save this file for later reference and implementation.

## Goal

Allow polling staff to continue entering ballots during network outages, persist submissions locally, and automatically (or manually) reconcile them with the server when connectivity resumes. Keep UI responsive and provide clear feedback to users in Arabic (RTL) and support the existing dark/light theme.

---

## Recommended High-Level Approach

- Make the app a Progressive Web App (PWA) shell with a Service Worker so the UI loads while offline.
- Use IndexedDB (via a lightweight wrapper like `Dexie` or `idb`) to queue ballot submissions reliably on the device.
- Use Background Sync (Service Worker / Workbox) when available to automatically retry queued POSTs. Provide a robust fallback (manual sync and periodic retry) when background sync is unavailable.
- Implement a server-side batch endpoint to accept grouped submissions, and make server handling idempotent using idempotency tokens.

---

## Checklist

- [ ] Add PWA shell & Service Worker (Workbox recommended)
- [ ] Client queue using IndexedDB (Dexie or idb)
- [ ] Background sync + fallback retry logic
- [ ] Server batch endpoint + idempotency handling
- [ ] UI changes: offline banner, queue count, sync button (Arabic/RTL friendly)
- [ ] Security & encryption decisions for local PII
- [ ] Testing & rollout plan (field dry runs)

---

## Why this design

- IndexedDB is durable, transactional, and supports larger volumes than `localStorage`.
- Service Worker + Workbox simplifies caching and provides background sync / replay helpers.
- A server `batch` endpoint reduces network overhead and simplifies reconciliation on the backend.

---

## Implementation Notes & Code Snippets

1) Register a Service Worker (main entry)

```js
// resources/js/app.js (or your main entry file)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      await navigator.serviceWorker.register('/service-worker.js');
      console.log('Service Worker registered');
    } catch (e) {
      console.warn('SW register failed', e);
    }
  });
}
```

2) Simple IndexedDB queue using Dexie

```js
// resources/js/offline/queue.js
import Dexie from 'dexie';

export const db = new Dexie('psp4_offline');
db.version(1).stores({
  queue: '++id, idempotency_token, station_id, status, created_at'
});

export async function enqueueBallot(item) {
  const token = item.idempotency_token || crypto.randomUUID();
  await db.queue.add({ idempotency_token: token, station_id: item.station_id, payload: item, status: 'queued', created_at: Date.now() });
  return token;
}

export async function getQueued() { return await db.queue.toArray(); }
export async function removeQueued(id) { return await db.queue.delete(id); }
```

3) Workbox background sync example (service-worker.js)

```js
// Use workbox-build / workbox-cli to generate or include these helpers
import { registerRoute } from 'workbox-routing';
import { NetworkOnly } from 'workbox-strategies';
import { BackgroundSyncPlugin } from 'workbox-background-sync';

const bgSyncPlugin = new BackgroundSyncPlugin('ballotQueue', {
  maxRetentionTime: 24 * 60, // minutes
});

registerRoute(
  /\/api\/stations\/.*\/ballots/, // match ballot endpoints
  new NetworkOnly({ plugins: [bgSyncPlugin] }),
  'POST'
);
```

Workbox will store failed requests and replay them later. For greater control or batch uploads, you can implement a manual sync routine that reads from IndexedDB and posts to a batch endpoint.

4) Example Laravel batch endpoint (routes & controller)

```php
// routes/api.php
Route::post('stations/{station}/ballots/batch', [App\Http\Controllers\Api\BallotController::class, 'storeBatch']);

// In BallotController::storeBatch(Request $request, PollingStation $station) ...
// Validate array, iterate items, check idempotency token, create BallotEntry records, return per-item status.
```

Server-side idempotency options:

- Create a `ballot_submissions` table that stores `idempotency_token` (unique) and maps to created ballot IDs. When a token is present, skip duplicate creation and return the recorded result.
- Or, use a unique constraint on a server-side derived field if you can guarantee uniqueness.

---

## UX Requirements (RTL / Arabic / Dark Mode)

- Provide an offline indicator (e.g. banner or icon) in Arabic (e.g. `غير متصل بالشبكة`).
- Show queued count with Arabic label (e.g. `مُؤقت: 5 أوراق`).
- Add a button `مزامنة الآن` (Sync now) to trigger manual upload.
- Keep existing optimistic UI behavior; mark optimistic items clearly (yellow) and update them when server confirms.

---

## Security & Privacy

- Minimize PII stored locally. If storing PII is necessary, encrypt the payload using Web Crypto API and decrypt on server (manage keys securely).
- Include device identifier and user auth token with queued payloads so the server can verify origin.
- Ensure server logs the original device timestamp and idempotency token for audit.

---

## Testing & Rollout

- Test offline flows in Chrome DevTools (Network -> Offline).
- Test background sync by simulating offline then going online.
- Field dry runs with a few devices before production elections.

---

## Minimal Starter Implementation (fast path)

If you want a minimal, fast-to-ship solution:

1. Register a service worker that precaches the app shell so the Entry page loads offline.
2. Add the Dexie queue helper and call `enqueueBallot()` when a `fetch` to POST a ballot fails or when `!navigator.onLine`.
3. Add `POST /stations/{id}/ballots/batch` server endpoint that accepts arrays and is idempotent.
4. Add a small offline indicator and a `مزامنة الآن` button that attempts to flush the queue by calling the batch endpoint.

This provides reliable offline capture and a simple reconciliation path without full Workbox background sync initially.

---

If you want, I can implement the minimal starter now (service worker registration, Dexie queue, batch endpoint scaffold, and a small UI indicator) and wire it into `EntryGrid.vue`. Tell me to proceed and I'll create the necessary files and patches.
