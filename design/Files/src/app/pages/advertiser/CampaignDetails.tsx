import { FormEvent, useCallback, useEffect, useState } from 'react';
import { useParams, Link } from 'react-router';
import { ArrowLeft, Calendar, Car, ExternalLink, MapPin, Loader2, Upload } from 'lucide-react';
import { apiFormData, apiJson } from '@/lib/api';

type VehicleRow = { id: number; brand: string; model: string; imei: string };
type CampaignVehicleRow = {
  id: number;
  vehicle_id: number;
  placement_size_class: string;
  agreed_price: string | null;
  vehicle?: VehicleRow;
};
type CampaignDetail = {
  id: number;
  name: string;
  status: string;
  description: string | null;
  start_date: string | null;
  end_date: string | null;
  campaign_vehicles?: CampaignVehicleRow[];
};

type ProofRow = {
  id: number;
  vehicle_id: number;
  status: string;
  comment: string | null;
  url: string;
  created_at: string | null;
  vehicle: VehicleRow | null;
};

export function AdvertiserCampaignDetails() {
  const { id: campaignId } = useParams();
  const [campaign, setCampaign] = useState<CampaignDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [catalog, setCatalog] = useState<VehicleRow[]>([]);
  const [attachVid, setAttachVid] = useState('');
  const [attachSize, setAttachSize] = useState('M');
  const [attachErr, setAttachErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [proofVid, setProofVid] = useState('');
  const [proofMsg, setProofMsg] = useState<string | null>(null);
  const [proofs, setProofs] = useState<ProofRow[]>([]);

  const loadProofs = useCallback(() => {
    if (!campaignId) return;
    apiJson<{ data: ProofRow[] }>(`/api/advertiser/campaigns/${campaignId}/proofs`)
      .then((r) => setProofs(r.data ?? []))
      .catch(() => setProofs([]));
  }, [campaignId]);

  const loadCampaign = useCallback((showFullSpinner = true) => {
    if (!campaignId) return;
    if (showFullSpinner) setLoading(true);
    setError(null);
    apiJson<CampaignDetail>(`/api/advertiser/campaigns/${campaignId}`)
      .then((data) => {
        setCampaign(data);
        const rows = data.campaign_vehicles ?? [];
        const ids = new Set(rows.map((r) => r.vehicle_id));
        if (ids.size) {
          setProofVid((prev) => prev || String([...ids][0]));
        }
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [campaignId]);

  useEffect(() => {
    loadCampaign(true);
  }, [campaignId, loadCampaign]);

  useEffect(() => {
    loadProofs();
  }, [loadProofs]);

  useEffect(() => {
    apiJson<{ data: VehicleRow[] }>('/api/advertiser/vehicles?per_page=200')
      .then((res) => setCatalog(res.data ?? []))
      .catch(() => setCatalog([]));
  }, []);

  async function onAttach(e: FormEvent) {
    e.preventDefault();
    if (!campaignId || !attachVid) return;
    setAttachErr(null);
    setBusy(true);
    try {
      await apiJson(`/api/advertiser/campaigns/${campaignId}/vehicles`, {
        method: 'POST',
        body: JSON.stringify({
          vehicle_id: Number(attachVid),
          placement_size_class: attachSize,
        }),
      });
      setAttachVid('');
      loadCampaign(false);
    } catch (err) {
      setAttachErr(err instanceof Error ? err.message : 'Failed to attach');
    } finally {
      setBusy(false);
    }
  }

  async function onDetach(cvId: number) {
    if (!campaignId) return;
    setBusy(true);
    try {
      await apiJson(`/api/advertiser/campaigns/${campaignId}/vehicles/${cvId}`, { method: 'DELETE' });
      loadCampaign(false);
    } finally {
      setBusy(false);
    }
  }

  async function onProofUpload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!campaignId || !proofVid) return;
    const input = (e.target as HTMLFormElement).elements.namedItem('proof') as HTMLInputElement;
    const file = input?.files?.[0];
    if (!file) {
      setProofMsg('Choose a file.');
      return;
    }
    setProofMsg(null);
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('vehicle_id', proofVid);
      fd.append('file', file);
      await apiFormData(`/api/advertiser/campaigns/${campaignId}/proofs`, fd);
      setProofMsg('Upload successful.');
      input.value = '';
      loadProofs();
    } catch (err) {
      setProofMsg(err instanceof Error ? err.message : 'Upload failed');
    } finally {
      setBusy(false);
    }
  }

  if (loading && !campaign) {
    return (
      <div className="p-8 flex justify-center items-center min-h-[40vh] gap-3">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
        <span className="text-muted-foreground">Loading…</span>
      </div>
    );
  }

  if (error || !campaign) {
    return (
      <div className="p-8">
        <Link to="/advertiser/campaigns" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6">
          <ArrowLeft className="w-4 h-4" />
          Back
        </Link>
        <p className="text-destructive">{error ?? 'Not found'}</p>
      </div>
    );
  }

  const rows = campaign.campaign_vehicles ?? [];
  const linkedIds = new Set(rows.map((r) => r.vehicle_id));
  const availableCatalog = catalog.filter((v) => !linkedIds.has(v.id));

  const fmt = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

  return (
    <div className="p-8 max-w-5xl">
      <Link to="/advertiser/campaigns" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6 hover:text-foreground">
        <ArrowLeft className="w-4 h-4" />
        Back to campaigns
      </Link>

      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
          <h1 className="text-3xl mb-2">{campaign.name}</h1>
          <p className="text-muted-foreground capitalize">Status: {campaign.status}</p>
          <div className="flex items-center gap-2 text-sm text-muted-foreground mt-2">
            <Calendar className="w-4 h-4" />
            {fmt(campaign.start_date)} — {fmt(campaign.end_date)}
          </div>
        </div>
        <Link
          to={`/advertiser/heatmap?campaignId=${campaign.id}&campaignName=${encodeURIComponent(campaign.name)}${campaign.start_date ? `&dateFrom=${campaign.start_date}` : ''}${campaign.end_date ? `&dateTo=${campaign.end_date}` : ''}&vehicle=all`}
          className="px-4 py-2 bg-accent text-accent-foreground rounded-lg text-sm flex items-center gap-2 shrink-0"
        >
          <MapPin className="w-4 h-4" />
          Heatmap
        </Link>
      </div>

      {campaign.description ? <p className="mb-8 text-muted-foreground max-w-3xl">{campaign.description}</p> : null}

      <div className="mb-8 rounded-lg border border-border bg-card p-5">
        <h2 className="text-lg font-semibold mb-2">Attach vehicle</h2>
        <p className="text-xs text-muted-foreground mb-4">Placement S / M / L / XL — price from platform policy.</p>
        <form className="flex flex-wrap items-end gap-3" onSubmit={(ev) => void onAttach(ev)}>
          <div>
            <label className="block text-xs font-medium text-muted-foreground mb-1">Vehicle</label>
            <select
              value={attachVid}
              onChange={(e) => setAttachVid(e.target.value)}
              required
              className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input min-w-[220px] text-sm"
            >
              <option value="">Select…</option>
              {availableCatalog.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.brand} {v.model} — {v.imei}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-muted-foreground mb-1">Placement</label>
            <select
              value={attachSize}
              onChange={(e) => setAttachSize(e.target.value)}
              className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm w-24"
            >
              {(['S', 'M', 'L', 'XL'] as const).map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </div>
          <button type="submit" disabled={busy} className="px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium disabled:opacity-50">
            Attach
          </button>
        </form>
        {attachErr ? <p className="mt-2 text-sm text-destructive">{attachErr}</p> : null}
      </div>

      <h2 className="text-xl mb-3">Vehicles on campaign</h2>
      {rows.length === 0 ? (
        <p className="text-muted-foreground mb-8">No vehicles yet. Attach from catalog above.</p>
      ) : (
        <div className="mb-8 border border-border rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Vehicle</th>
                <th className="text-left p-3">IMEI</th>
                <th className="text-left p-3">Placement</th>
                <th className="text-left p-3">Agreed</th>
                <th className="p-3 w-24" />
              </tr>
            </thead>
            <tbody>
              {rows.map((cv) => (
                <tr key={cv.id} className="border-t border-border">
                  <td className="p-3">
                    <div className="flex items-center gap-2">
                      <Car className="w-4 h-4 text-muted-foreground shrink-0" />
                      {cv.vehicle ? `${cv.vehicle.brand} ${cv.vehicle.model}` : `#${cv.vehicle_id}`}
                    </div>
                  </td>
                  <td className="p-3 font-mono text-xs">{cv.vehicle?.imei ?? '—'}</td>
                  <td className="p-3">{cv.placement_size_class}</td>
                  <td className="p-3">{cv.agreed_price ?? '—'}</td>
                  <td className="p-3">
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void onDetach(cv.id)}
                      className="text-sm text-destructive hover:underline disabled:opacity-50"
                    >
                      Remove
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <div className="rounded-lg border border-border bg-card p-5">
        <h2 className="text-lg font-semibold mb-2 flex items-center gap-2">
          <Upload className="w-5 h-5" />
          Campaign proof
        </h2>
        <p className="text-xs text-muted-foreground mb-4">Image or PDF for a vehicle already on this campaign.</p>
        <form className="flex flex-wrap items-end gap-3" onSubmit={(ev) => void onProofUpload(ev)}>
          <div>
            <label className="block text-xs font-medium text-muted-foreground mb-1">Vehicle</label>
            <select
              value={proofVid}
              onChange={(e) => setProofVid(e.target.value)}
              required
              className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input min-w-[220px] text-sm"
            >
              <option value="">Select…</option>
              {rows.map((cv) => (
                <option key={cv.id} value={cv.vehicle_id}>
                  {cv.vehicle ? `${cv.vehicle.brand} ${cv.vehicle.model}` : `Vehicle #${cv.vehicle_id}`}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-muted-foreground mb-1">File</label>
            <input name="proof" type="file" accept="image/*,.pdf" required className="block text-sm max-w-[240px]" />
          </div>
          <button type="submit" disabled={busy || rows.length === 0} className="px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium disabled:opacity-50">
            Upload
          </button>
        </form>
        {proofMsg ? <p className="mt-3 text-sm text-muted-foreground">{proofMsg}</p> : null}
      </div>

      <div className="mt-8 rounded-lg border border-border bg-card p-5">
        <h2 className="text-lg font-semibold mb-3">Uploaded proofs</h2>
        {proofs.length === 0 ? (
          <p className="text-sm text-muted-foreground">No proofs yet.</p>
        ) : (
          <div className="border border-border rounded-lg overflow-x-auto">
            <table className="w-full text-sm min-w-[520px]">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-3">Vehicle</th>
                  <th className="text-left p-3">Status</th>
                  <th className="text-left p-3">Uploaded</th>
                  <th className="text-left p-3">File</th>
                </tr>
              </thead>
              <tbody>
                {proofs.map((p) => (
                  <tr key={p.id} className="border-t border-border">
                    <td className="p-3">
                      {p.vehicle ? `${p.vehicle.brand} ${p.vehicle.model}` : `#${p.vehicle_id}`}
                    </td>
                    <td className="p-3 capitalize">{p.status}</td>
                    <td className="p-3 text-muted-foreground">
                      {p.created_at ? new Date(p.created_at).toLocaleString() : '—'}
                    </td>
                    <td className="p-3">
                      <a
                        href={p.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 text-primary hover:underline"
                      >
                        Open <ExternalLink className="w-3.5 h-3.5" />
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {proofs.some((p) => p.comment) ? (
          <p className="mt-3 text-xs text-muted-foreground">Moderator comments appear in Filament admin.</p>
        ) : null}
      </div>
    </div>
  );
}
