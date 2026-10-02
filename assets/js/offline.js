/* ==========================================================================
   EduGT — Modo offline parcial (RF-25..27, RB-13, RB-14)
   - Descarga el bundle de lecciones (solo lectura) con versión/hash
   - En IndexedDB: persiste lecciones + progreso de lectura pendiente de sync
   - Al reconectar: cola de sincronización (solo lectura; un quiz offline NO existe)
   ========================================================================== */
'use strict';

const DB_NAME = 'edugt-offline';
const DB_VERSION = 1;
const STORE_LESSONS = 'lessons';
const STORE_QUEUE = 'sync_queue';
const STORE_META = 'meta';

/** Abre la BD (createObjectStore en onupgradeneeded). */
function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(STORE_LESSONS)) {
        db.createObjectStore(STORE_LESSONS, { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains(STORE_QUEUE)) {
        db.createObjectStore(STORE_QUEUE, { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains(STORE_META)) {
        db.createObjectStore(STORE_META, { keyPath: 'key' });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function putAll(store, items) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite');
    items.forEach((it) => tx.objectStore(store).put(it));
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

async function getAll(store) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readonly');
    const req = tx.objectStore(store).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function clearStore(store) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite');
    tx.objectStore(store).clear();
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

/* ---------------- API del módulo (consumida por lección.html offline) ---------------- */

/** Descarga el bundle del servidor; si no hay conexión, retorna null. */
async function downloadBundle() {
  const { data } = await api('/offline/bundle');
  await clearStore(STORE_LESSONS);
  await putAll(STORE_LESSONS, data.data.lessons);
  await putAll(STORE_META, [{ key: 'bundle_generated_at', value: data.data.generated_at }]);
  return data.data;
}

/** Lista de lecciones offline-disponibles. */
async function offlineLessons() {
  return getAll(STORE_LESSONS);
}

/** Lee el hash de versión guardado. */
async function bundleStamp() {
  const meta = await getAll(STORE_META);
  return meta.find((m) => m.key === 'bundle_generated_at')?.value ?? null;
}

/** Encola lectura offline (RB-14: solo tiempo de lectura; numpy del quiz NO existe). */
async function queueLessonRead(lessonId, timeSpentSeconds, lastVisitedAt = new Date().toISOString()) {
  await putAll(STORE_QUEUE, [{ lesson_id: lessonId, time_spent_seconds: timeSpentSeconds, last_visited_at: lastVisitedAt }]);
}

/**
 * Replica la cola al servidor cuando hay conexión.
 * RB-13: si un item trae quiz_*, el servidor lo cuenta como rechazado y no registra nada.
 * @returns {Promise<{accepted:int, rejected:int} | null>} null si no hay conexión
 */
async function flushQueue() {
  const items = await getAll(STORE_QUEUE);
  if (items.length === 0) return { accepted: 0, rejected: 0 };

  try {
    const { data } = await api('/offline/sync', { method: 'POST', body: { items } });
    await clearStore(STORE_QUEUE);
    return data;
  } catch (err) {
    if (err.status === 401) { Auth.clear(); window.location.href = 'index.html'; }
    return null; // sin conexión — la cola espera
  }
}

/** ¿Estamos offline? (navigator.onLine + ping) */
function online() {
  return navigator.onLine;
}

// Reset de la cola cuando vuelve la conexión
window.addEventListener('online', async () => {
  const result = await flushQueue();
  if (result) {
    window.dispatchEvent(new CustomEvent('edugt:synced', { detail: result }));
  }
});
