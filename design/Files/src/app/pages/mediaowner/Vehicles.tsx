import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';
import { Car, Loader2, Plus } from 'lucide-react';
import { apiFormData, apiJson, storageUrl } from '@/lib/api';
import { fetchVehicleMeta, type VehicleFieldOption } from '@/lib/vehicleMeta';

type CampaignBrief = {
  id: number;
  name: string;
  status: string;
  advertiser?: { name: string; company_name: string | null } | null;
};

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  year: number | null;
  color_key?: string | null;
  color_label?: string | null;
  status: string;
  status_label?: string;
  campaigns?: CampaignBrief[];
  image_path?: string | null;
};

export function MediaOwnerVehicles() {
  const [rows, setRows] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAdd, setShowAdd] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    brand: '',
    model: '',
    imei: '',
    year: '',
    color_key: '',
    status: 'active',
  });
  const [meta, setMeta] = useState<{ colors: VehicleFieldOption[]; fleet_statuses: VehicleFieldOption[] } | null>(
    null
  );

  const load = useCallback(() => {
    setLoading(true);
    apiJson<{ data: Vehicle[] }>('/api/media-owner/vehicles')
      .then((r) => setRows(r.data ?? []))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchVehicleMeta()
      .then((m) => setMeta({ colors: m.colors, fleet_statuses: m.fleet_statuses }))
      .catch(() => setMeta({ colors: [], fleet_statuses: [] }));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function onAdd(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        brand: form.brand,
        model: form.model,
        imei: form.imei,
        year: form.year ? Number(form.year) : null,
      };
      if (form.color_key) {
        payload.color_key = form.color_key;
      }
      if (form.status) {
        payload.status = form.status;
      }
      await apiJson('/api/media-owner/vehicles', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setForm({ brand: '', model: '', imei: '', year: '', color_key: '', status: 'active' });
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
        <form
          onSubmit={(e) => void onAdd(e)}
          className="mb-8 bg-card border border-border rounded-lg p-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 max-w-6xl"
        >
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
          <select
            value={form.color_key}
            onChange={(e) => setForm((f) => ({ ...f, color_key: e.target.value }))}
            className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
            aria-label="Body color"
          >
            <option value="">Color (optional)</option>
            {(meta?.colors ?? []).map((c) => (
              <option key={c.key} value={c.key}>
                {c.label}
              </option>
            ))}
          </select>
          <select
            value={form.status}
            onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
            className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
            aria-label="Fleet status"
          >
            {(meta?.fleet_statuses ?? []).map((s) => (
              <option key={s.key} value={s.key}>
                {s.label}
              </option>
            ))}
          </select>
          <button
            type="submit"
            disabled={saving}
            className="lg:col-span-6 py-2 bg-primary text-primary-foreground rounded-lg disabled:opacity-50"
          >
            {saving ? 'Saving…' : 'Save vehicle'}
          </button>
        </form>
      ) : null}

      {rows.length === 0 ? (
        <p className="text-muted-foreground">No vehicles yet. Add one above.</p>
      ) : (
        <div className="border border-border rounded-lg overflow-x-auto">
          <table className="w-full text-sm min-w-[900px]">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Vehicle</th>
                <th className="text-left p-3">IMEI</th>
                <th className="text-left p-3">Color</th>
                <th className="text-left p-3">Status</th>
                <th className="text-left p-3">Campaigns</th>
                <th className="text-left p-3">Image</th>
                <th className="p-3" />
              </tr>
            </thead>
            <tbody>
              {rows.map((v) => {
                const thumb = storageUrl(v.image_path);
                const statusText = v.status_label ?? v.status.replace(/_/g, ' ');
                const colorText = v.color_label ?? '—';
                const campaignNames = (v.campaigns ?? []).map((c) => c.name).join(', ');
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
                    <td className="p-3">{colorText}</td>
                    <td className="p-3">{statusText}</td>
                    <td className="p-3 max-w-[200px] truncate text-muted-foreground" title={campaignNames || undefined}>
                      {campaignNames || '—'}
                    </td>
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
