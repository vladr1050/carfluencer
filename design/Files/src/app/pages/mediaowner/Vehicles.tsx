import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';
import { Car, Loader2, Plus } from 'lucide-react';
import { apiFormData, apiJson, storageUrl } from '@/lib/api';

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  year: number | null;
  status: string;
  image_path?: string | null;
};

export function MediaOwnerVehicles() {
  const [rows, setRows] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAdd, setShowAdd] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ brand: '', model: '', imei: '', year: '' });

  const load = useCallback(() => {
    setLoading(true);
    apiJson<{ data: Vehicle[] }>('/api/media-owner/vehicles')
      .then((r) => setRows(r.data ?? []))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function onAdd(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await apiJson('/api/media-owner/vehicles', {
        method: 'POST',
        body: JSON.stringify({
          brand: form.brand,
          model: form.model,
          imei: form.imei,
          year: form.year ? Number(form.year) : null,
        }),
      });
      setForm({ brand: '', model: '', imei: '', year: '' });
      setShowAdd(false);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to add');
    } finally {
      setSaving(false);
    }
  }

  async function onImage(id: number, file: File | null) {
    if (!file) return;
    const fd = new FormData();
    fd.append('image', file);
    try {
      await apiFormData(`/api/media-owner/vehicles/${id}/image`, fd);
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Image upload failed');
    }
  }

  if (loading && rows.length === 0) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
        <span className="text-muted-foreground">Loading vehicles…</span>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-3xl mb-2">Vehicles</h1>
          <p className="text-muted-foreground">IMEI required for telemetry. Data from Laravel API.</p>
        </div>
        <button
          type="button"
          onClick={() => setShowAdd(!showAdd)}
          className="flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-lg"
        >
          <Plus className="w-5 h-5" />
          Add vehicle
        </button>
      </div>

      {error ? <p className="text-destructive text-sm mb-4">{error}</p> : null}

      {showAdd ? (
        <form onSubmit={(e) => void onAdd(e)} className="mb-8 bg-card border border-border rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 gap-3 max-w-4xl">
          <input
            required
            placeholder="Brand"
            value={form.brand}
            onChange={(e) => setForm((f) => ({ ...f, brand: e.target.value }))}
            className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input"
          />
          <input
            required
            placeholder="Model"
            value={form.model}
            onChange={(e) => setForm((f) => ({ ...f, model: e.target.value }))}
            className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input"
          />
          <input
            required
            placeholder="IMEI (unique)"
            value={form.imei}
            onChange={(e) => setForm((f) => ({ ...f, imei: e.target.value }))}
            className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input font-mono text-sm"
          />
          <input
            placeholder="Year"
            value={form.year}
            onChange={(e) => setForm((f) => ({ ...f, year: e.target.value }))}
            className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input"
          />
          <button type="submit" disabled={saving} className="md:col-span-4 py-2 bg-primary text-primary-foreground rounded-lg disabled:opacity-50">
            {saving ? 'Saving…' : 'Save vehicle'}
          </button>
        </form>
      ) : null}

      {rows.length === 0 ? (
        <p className="text-muted-foreground">No vehicles yet. Add one above.</p>
      ) : (
        <div className="border border-border rounded-lg overflow-x-auto">
          <table className="w-full text-sm min-w-[640px]">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Vehicle</th>
                <th className="text-left p-3">IMEI</th>
                <th className="text-left p-3">Status</th>
                <th className="text-left p-3">Image</th>
                <th className="p-3" />
              </tr>
            </thead>
            <tbody>
              {rows.map((v) => {
                const thumb = storageUrl(v.image_path);
                return (
                  <tr key={v.id} className="border-t border-border">
                    <td className="p-3">
                      <div className="flex items-center gap-2">
                        <Car className="w-4 h-4 opacity-60" />
                        <span>
                          {v.brand} {v.model}
                          {v.year ? ` · ${v.year}` : ''}
                        </span>
                      </div>
                    </td>
                    <td className="p-3 font-mono text-xs">{v.imei}</td>
                    <td className="p-3 capitalize">{v.status}</td>
                    <td className="p-3">
                      <div className="flex items-center gap-2">
                        {thumb ? <img src={thumb} alt="" className="w-10 h-10 rounded object-cover border border-border" /> : null}
                        <label className="text-xs text-primary cursor-pointer hover:underline">
                          Upload
                          <input
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => void onImage(v.id, e.target.files?.[0] ?? null)}
                          />
                        </label>
                      </div>
                    </td>
                    <td className="p-3 text-right">
                      <Link to={`/mediaowner/vehicles/${v.id}`} className="text-primary text-sm font-medium hover:underline">
                        Details
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
