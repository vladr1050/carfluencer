import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { apiJson, storageUrl } from '@/lib/api';
import { fetchVehicleMeta, type VehicleFieldOption, optionLabel } from '@/lib/vehicleMeta';

type CampaignBrief = {
  id: number;
  name: string;
  status: string;
  start_date?: string | null;
  end_date?: string | null;
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
  notes: string | null;
  image_path?: string | null;
  campaigns?: CampaignBrief[];
};

export function MediaOwnerVehicleDetails() {
  const { id } = useParams();
  const [v, setV] = useState<Vehicle | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [meta, setMeta] = useState<{ colors: VehicleFieldOption[]; fleet_statuses: VehicleFieldOption[] } | null>(
    null
  );
  const [editColor, setEditColor] = useState('');
  const [editStatus, setEditStatus] = useState('active');
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  useEffect(() => {
    fetchVehicleMeta()
      .then((m) => setMeta({ colors: m.colors, fleet_statuses: m.fleet_statuses }))
      .catch(() => setMeta({ colors: [], fleet_statuses: [] }));
  }, []);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    apiJson<Vehicle>(`/api/media-owner/vehicles/${id}`)
      .then((data) => {
        setV(data);
        setEditColor(data.color_key ?? '');
        setEditStatus(data.status);
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id]);

  async function onSaveEdit(e: FormEvent) {
    e.preventDefault();
    if (!id) return;
    setSaveMsg(null);
    setSaving(true);
    try {
      const updated = await apiJson<Vehicle>(`/api/media-owner/vehicles/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
          color_key: editColor || null,
          status: editStatus,
        }),
      });
      setV(updated);
      setSaveMsg('Saved.');
    } catch (err) {
      setSaveMsg(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin" />
      </div>
    );
  }

  if (error || !v) {
    return (
      <div className="p-8">
        <Link to="/mediaowner/vehicles" className="text-sm text-muted-foreground">
          ← Back
        </Link>
        <p className="text-destructive mt-4">{error ?? 'Not found'}</p>
      </div>
    );
  }

  const img = storageUrl(v.image_path);
  const statusText = v.status_label ?? optionLabel(meta?.fleet_statuses ?? [], v.status, v.status);
  const colorText = v.color_label ?? optionLabel(meta?.colors ?? [], v.color_key, '—');

  return (
    <div className="p-8 max-w-5xl">
      <Link to="/mediaowner/vehicles" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6 hover:text-foreground">
        <ArrowLeft className="w-4 h-4" />
        Back to vehicles
      </Link>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="aspect-video rounded-lg border border-border bg-muted overflow-hidden flex items-center justify-center">
          {img ? <img src={img} alt="" className="w-full h-full object-cover" /> : <span className="text-muted-foreground text-sm">No image</span>}
        </div>
        <div>
          <h1 className="text-3xl mb-2">
            {v.brand} {v.model}
          </h1>
          <p className="font-mono text-sm text-muted-foreground mb-4">{v.imei}</p>
          <ul className="space-y-2 text-sm mb-8">
            <li>
              <span className="text-muted-foreground">Year:</span> {v.year ?? '—'}
            </li>
            <li>
              <span className="text-muted-foreground">Color:</span> {colorText}
            </li>
            <li>
              <span className="text-muted-foreground">Status:</span> {statusText}
            </li>
            {v.notes ? (
              <li>
                <span className="text-muted-foreground">Notes:</span> {v.notes}
              </li>
            ) : null}
          </ul>

          <h2 className="text-lg font-semibold mb-2">Campaign membership</h2>
          {!v.campaigns?.length ? (
            <p className="text-sm text-muted-foreground mb-8">Not linked to any campaign yet.</p>
          ) : (
            <ul className="space-y-2 mb-8 text-sm border border-border rounded-lg p-4 bg-card">
              {v.campaigns.map((c) => (
                <li key={c.id} className="flex flex-wrap items-baseline justify-between gap-2">
                  <Link to={`/mediaowner/campaigns/${c.id}`} className="text-primary font-medium hover:underline">
                    {c.name}
                  </Link>
                  <span className="text-muted-foreground capitalize text-xs">{c.status}</span>
                  {c.advertiser ? (
                    <span className="w-full text-xs text-muted-foreground">
                      Advertiser: {c.advertiser.company_name || c.advertiser.name}
                    </span>
                  ) : null}
                </li>
              ))}
            </ul>
          )}

          <div className="rounded-lg border border-border bg-card p-4">
            <h2 className="text-lg font-semibold mb-3">Edit color &amp; status</h2>
            <form className="flex flex-col sm:flex-row flex-wrap items-end gap-3" onSubmit={(ev) => void onSaveEdit(ev)}>
              <div>
                <label className="block text-xs font-medium text-muted-foreground mb-1">Color</label>
                <select
                  value={editColor}
                  onChange={(e) => setEditColor(e.target.value)}
                  className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input min-w-[180px] text-sm"
                >
                  <option value="">Not set</option>
                  {(meta?.colors ?? []).map((c) => (
                    <option key={c.key} value={c.key}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-muted-foreground mb-1">Fleet status</label>
                <select
                  value={editStatus}
                  onChange={(e) => setEditStatus(e.target.value)}
                  className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input min-w-[180px] text-sm"
                >
                  {(meta?.fleet_statuses ?? []).map((s) => (
                    <option key={s.key} value={s.key}>
                      {s.label}
                    </option>
                  ))}
                </select>
              </div>
              <button
                type="submit"
                disabled={saving}
                className="px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium disabled:opacity-50"
              >
                {saving ? 'Saving…' : 'Save'}
              </button>
            </form>
            {saveMsg ? <p className="mt-2 text-sm text-muted-foreground">{saveMsg}</p> : null}
          </div>
        </div>
      </div>
    </div>
  );
}
