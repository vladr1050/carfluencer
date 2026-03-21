const TOKEN_KEY = 'carfluencer_token';

/**
 * API base URL for fetch().
 * - If `VITE_API_URL` is set → use it (direct to Laravel).
 * - In dev, if unset → `''` so requests go to the Vite dev server and are **proxied** to Laravel
 *   (avoids Safari "Load failed" when the page is `localhost:5174` but API was `127.0.0.1:8000`).
 * - Production build without env → fallback (set VITE_API_URL for real deploys).
 */
export function getApiBase(): string {
  const raw = import.meta.env.VITE_API_URL;
  if (typeof raw === 'string' && raw.trim() !== '') {
    return raw.replace(/\/$/, '');
  }
  if (import.meta.env.DEV) {
    return '';
  }
  return 'http://127.0.0.1:8000';
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_KEY);
  }
}

/** Human-readable message from Laravel JSON (422 validation, 403, etc.) */
function messageFromApiBody(body: unknown, status: number, rawText: string): string {
  if (!body || typeof body !== 'object') {
    return rawText ? `HTTP ${status}: ${rawText.slice(0, 240)}` : `Request failed (${status})`;
  }
  const b = body as Record<string, unknown>;

  if (b.errors && typeof b.errors === 'object' && b.errors !== null) {
    const errors = b.errors as Record<string, string[] | string>;
    for (const msgs of Object.values(errors)) {
      if (Array.isArray(msgs) && msgs[0]) return String(msgs[0]);
      if (typeof msgs === 'string') return msgs;
    }
  }

  if (typeof b.message === 'string' && b.message) {
    return b.message;
  }
  if (typeof b.error === 'string' && b.error) {
    return b.error;
  }

  return `Request failed (${status})`;
}

function parseJsonSafe(text: string, status: number): unknown {
  if (!text.trim()) {
    return null;
  }
  try {
    return JSON.parse(text) as unknown;
  } catch {
    throw new Error(
      `API returned non-JSON (HTTP ${status}). Check VITE_API_URL and that Laravel is running. Body: ${text.slice(0, 200)}`,
    );
  }
}

export function formatUnknownError(err: unknown): string {
  if (err instanceof Error) {
    return err.message;
  }
  if (typeof err === 'string') {
    return err;
  }
  return 'Unexpected error — open DevTools → Network and check the login request.';
}

export async function apiJson<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken();
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (!(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  let res: Response;
  try {
    res = await fetch(`${getApiBase()}${path}`, {
      ...options,
      headers,
    });
  } catch (e) {
    const base = getApiBase();
    const hint = base
      ? `Cannot reach API at ${base}. Is php artisan serve running?`
      : `Cannot reach API (Vite dev proxy → Laravel). Is \`php artisan serve\` on :8000? Remove VITE_API_URL from .env if you use Safari.`;
    throw new Error(`${hint} (${formatUnknownError(e)})`);
  }

  if (res.status === 204) {
    return undefined as T;
  }

  const text = await res.text();
  const body = parseJsonSafe(text, res.status);

  if (!res.ok) {
    throw new Error(messageFromApiBody(body, res.status, text));
  }

  return body as T;
}

export async function apiFormData<T>(path: string, formData: FormData): Promise<T> {
  const token = getToken();
  const headers = new Headers();
  headers.set('Accept', 'application/json');
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  let res: Response;
  try {
    res = await fetch(`${getApiBase()}${path}`, {
      method: 'POST',
      headers,
      body: formData,
    });
  } catch (e) {
    const base = getApiBase();
    const hint = base ? `Cannot reach API at ${base}.` : `Cannot reach API (Vite proxy). Is Laravel on :8000?`;
    throw new Error(`${hint} (${formatUnknownError(e)})`);
  }

  const text = await res.text();
  const body = parseJsonSafe(text, res.status);

  if (!res.ok) {
    throw new Error(messageFromApiBody(body, res.status, text));
  }

  return body as T;
}

/** Public URL for stored files (vehicle images, etc.) */
export function storageUrl(path: string | null | undefined): string | null {
  if (!path) {
    return null;
  }
  if (path.startsWith('http')) {
    return path;
  }
  return `${getApiBase()}/storage/${path.replace(/^\//, '')}`;
}
