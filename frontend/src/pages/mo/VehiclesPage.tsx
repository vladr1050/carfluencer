import { FormEvent, useEffect, useState } from 'react';
import { apiFormData, apiJson, getApiBase, type ApiState } from '../../api/client';
import { MediaOwnerShell } from '../../layouts/MediaOwnerShell';

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  year: number | null;
  imei: string;
  status: string;
  image_path: string | null;
};

export default function MoVehiclesPage(): JSX.Element {
  const [listState, setListState] = useState<ApiState<Vehicle[]>>({ status: 'idle' });
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [brand, setBrand] = useState('');
  const [model, setModel] = useState('');
  const [imei, setImei] = useState('');
  const [year, setYear] = useState('');

  function load(): void {
    setListState({ status: 'loading' });
    apiJson<{ data: Vehicle[] }>('/api/media-owner/vehicles')
      .then((res) => {
        const rows = res.data ?? [];
        setListState(rows.length ? { status: 'success', data: rows } : { status: 'empty' });
      })
      .catch((e: Error) => setListState({ status: 'error', message: e.message }));
  }

  useEffect(() => {
    load();
  }, []);

  async function onCreate(e: FormEvent): Promise<void> {
    e.preventDefault();
    setFormError(null);
    setSaving(true);
    try {
      await apiJson('/api/media-owner/vehicles', {
        method: 'POST',
        body: JSON.stringify({
          brand,
          model,
          imei,
          year: year ? Number(year) : undefined,
        }),
      });
      setBrand('');
      setModel('');
      setImei('');
      setYear('');
      load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setSaving(false);
    }
  }

  async function onPickImage(vehicleId: number, file: File | undefined): Promise<void> {
    if (!file) {
      return;
    }
    try {
      const fd = new FormData();
      fd.append('image', file);
      await apiFormData(`/api/media-owner/vehicles/${vehicleId}/image`, fd);
      load();
    } catch {
      /* toast optional */
    }
  }

  return (
    <MediaOwnerShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Vehicles</h1>
      <p className="mb-8 text-sm text-muted-foreground">IMEI is required for telemetry linkage.</p>

      <div className="mb-8 cf-card">
        <h2 className="mb-4 text-base font-semibold text-foreground">Add vehicle</h2>
        <form className="grid max-w-xl gap-4 sm:grid-cols-2" onSubmit={(e) => void onCreate(e)}>
          <div className="sm:col-span-1">
            <label className="cf-label">Brand</label>
            <input className="cf-input" value={brand} onChange={(e) => setBrand(e.target.value)} required />
          </div>
          <div className="sm:col-span-1">
            <label className="cf-label">Model</label>
            <input className="cf-input" value={model} onChange={(e) => setModel(e.target.value)} required />
          </div>
          <div className="sm:col-span-1">
            <label className="cf-label">IMEI</label>
            <input className="cf-input font-mono text-sm" value={imei} onChange={(e) => setImei(e.target.value)} required />
          </div>
          <div className="sm:col-span-1">
            <label className="cf-label">Year (optional)</label>
            <input className="cf-input" type="number" value={year} onChange={(e) => setYear(e.target.value)} />
          </div>
          <div className="sm:col-span-2">
            {formError ? <p className="mb-2 text-sm text-red-600">{formError}</p> : null}
            <button className="cf-btn" type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Add vehicle'}
            </button>
          </div>
        </form>
      </div>

      {listState.status === 'loading' ? <p className="text-muted-foreground">Loading list…</p> : null}
      {listState.status === 'error' ? <p className="text-red-600">{listState.message}</p> : null}
      {listState.status === 'empty' ? <p className="text-muted-foreground">No vehicles yet.</p> : null}
      {listState.status === 'success' ? (
        <div className="cf-card overflow-x-auto p-0">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Brand / model</th>
                <th className="px-4 py-3">IMEI</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Image</th>
              </tr>
            </thead>
            <tbody>
              {listState.data.map((v) => (
                <tr key={v.id} className="border-b border-border">
                  <td className="px-4 py-3 font-medium">
                    {v.brand} {v.model}
                  </td>
                  <td className="px-4 py-3 font-mono text-xs">{v.imei}</td>
                  <td className="px-4 py-3">{v.status}</td>
                  <td className="px-4 py-3">
                    <label className="cursor-pointer text-xs font-medium text-brand-magenta hover:underline">
                      {v.image_path ? 'Replace' : 'Upload'}
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => {
                          void onPickImage(v.id, e.target.files?.[0]);
                          e.target.value = '';
                        }}
                      />
                    </label>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="border-t border-border px-4 py-3 text-xs text-muted-foreground">
            API: <code className="rounded bg-muted px-1">{getApiBase()}/api/media-owner/vehicles/&lt;id&gt;/image</code>
          </p>
        </div>
      ) : null}
    </MediaOwnerShell>
  );
}
