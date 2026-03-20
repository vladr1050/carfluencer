import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { apiFormData, apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

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
  campaign_vehicles?: CampaignVehicleRow[];
};

export default function AdvCampaignDetailPage(): JSX.Element {
  const { campaignId } = useParams<{ campaignId: string }>();
  const [campState, setCampState] = useState<ApiState<CampaignDetail>>({ status: 'idle' });
  const [catalog, setCatalog] = useState<VehicleRow[]>([]);
  const [attachVid, setAttachVid] = useState('');
  const [attachSize, setAttachSize] = useState('M');
  const [attachErr, setAttachErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [proofVid, setProofVid] = useState('');
  const [proofMsg, setProofMsg] = useState<string | null>(null);

  function loadCampaign(): void {
    if (!campaignId) {
      return;
    }
    setCampState({ status: 'loading' });
    apiJson<CampaignDetail>(`/api/advertiser/campaigns/${campaignId}`)
      .then((data) => {
        setCampState({ status: 'success', data });
        const rows = data.campaign_vehicles ?? [];
        const ids = new Set(rows.map((cv) => cv.vehicle_id));
        if (ids.size && !proofVid) {
          setProofVid(String([...ids][0]));
        }
      })
      .catch((e: Error) => setCampState({ status: 'error', message: e.message }));
  }

  useEffect(() => {
    loadCampaign();
  }, [campaignId]);

  useEffect(() => {
    apiJson<{ data: VehicleRow[] }>('/api/advertiser/vehicles?per_page=200')
      .then((res) => setCatalog(res.data ?? []))
      .catch(() => setCatalog([]));
  }, []);

  async function onAttach(e: FormEvent): Promise<void> {
    e.preventDefault();
    if (!campaignId || !attachVid) {
      return;
    }
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
      loadCampaign();
    } catch (err) {
      setAttachErr(err instanceof Error ? err.message : 'Failed to attach');
    } finally {
      setBusy(false);
    }
  }

  async function onDetach(cvId: number): Promise<void> {
    if (!campaignId) {
      return;
    }
    setBusy(true);
    try {
      await apiJson(`/api/advertiser/campaigns/${campaignId}/vehicles/${cvId}`, { method: 'DELETE' });
      loadCampaign();
    } finally {
      setBusy(false);
    }
  }

  async function onProofUpload(e: FormEvent<HTMLFormElement>): Promise<void> {
    e.preventDefault();
    if (!campaignId || !proofVid) {
      return;
    }
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
    } catch (err) {
      setProofMsg(err instanceof Error ? err.message : 'Upload failed');
    } finally {
      setBusy(false);
    }
  }

  const cvs = campState.status === 'success' ? (campState.data.campaign_vehicles ?? []) : [];
  const linkedIds = new Set(cvs.map((c) => c.vehicle_id));
  const availableCatalog = catalog.filter((v) => !linkedIds.has(v.id));

  return (
    <AdvertiserShell>
      <Link to="/advertiser/campaigns" className="mb-4 inline-block text-sm font-medium text-muted-foreground hover:text-foreground">
        ← Back to campaigns
      </Link>

      {campState.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {campState.status === 'error' ? <p className="text-red-600">{campState.message}</p> : null}
      {campState.status === 'success' ? (
        <>
          <h1 className="mb-2 text-2xl font-bold text-foreground">{campState.data.name}</h1>
          <p className="mb-6 text-sm text-muted-foreground">Status: {campState.data.status}</p>

          <div className="mb-8 cf-card">
            <h2 className="mb-3 text-base font-semibold text-foreground">Attach vehicle</h2>
            <p className="mb-4 text-xs text-muted-foreground">Select placement size class (S / M / L / XL). Price defaults from policy.</p>
            <form className="flex flex-wrap items-end gap-3" onSubmit={(ev) => void onAttach(ev)}>
              <div>
                <label className="cf-label" htmlFor="veh">
                  Vehicle
                </label>
                <select id="veh" className="cf-input min-w-[200px]" value={attachVid} onChange={(e) => setAttachVid(e.target.value)} required>
                  <option value="">Select…</option>
                  {availableCatalog.map((v) => (
                    <option key={v.id} value={v.id}>
                      {v.brand} {v.model} — {v.imei}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="cf-label" htmlFor="sz">
                  Placement
                </label>
                <select id="sz" className="cf-input w-28" value={attachSize} onChange={(e) => setAttachSize(e.target.value)}>
                  {(['S', 'M', 'L', 'XL'] as const).map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </div>
              <button type="submit" className="cf-btn" disabled={busy}>
                Attach
              </button>
            </form>
            {attachErr ? <p className="mt-2 text-sm text-red-600">{attachErr}</p> : null}
          </div>

          <h2 className="mb-3 text-lg font-semibold text-foreground">Vehicles on campaign</h2>
          {!cvs.length ? (
            <p className="mb-8 text-sm text-muted-foreground">No vehicles yet.</p>
          ) : (
            <div className="mb-8 cf-card overflow-x-auto p-0">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3">Vehicle</th>
                    <th className="px-4 py-3">IMEI</th>
                    <th className="px-4 py-3">Placement</th>
                    <th className="px-4 py-3">Agreed</th>
                    <th className="px-4 py-3" />
                  </tr>
                </thead>
                <tbody>
                  {cvs.map((cv) => (
                    <tr key={cv.id} className="border-b border-border">
                      <td className="px-4 py-3 font-medium">
                        {cv.vehicle ? `${cv.vehicle.brand} ${cv.vehicle.model}` : `#${cv.vehicle_id}`}
                      </td>
                      <td className="px-4 py-3 font-mono text-xs">{cv.vehicle?.imei ?? '—'}</td>
                      <td className="px-4 py-3">{cv.placement_size_class}</td>
                      <td className="px-4 py-3">{cv.agreed_price ?? '—'}</td>
                      <td className="px-4 py-3">
                        <button type="button" className="text-sm font-medium text-red-600 hover:underline" disabled={busy} onClick={() => void onDetach(cv.id)}>
                          Remove
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div className="cf-card">
            <h2 className="mb-3 text-base font-semibold text-foreground">Upload campaign proof</h2>
            <p className="mb-4 text-xs text-muted-foreground">Image or PDF for a vehicle that is already on this campaign.</p>
            <form className="flex flex-wrap items-end gap-3" onSubmit={(ev) => void onProofUpload(ev)}>
              <div>
                <label className="cf-label" htmlFor="pv">
                  Vehicle on campaign
                </label>
                <select id="pv" className="cf-input min-w-[220px]" value={proofVid} onChange={(e) => setProofVid(e.target.value)} required>
                  <option value="">Select…</option>
                  {cvs.map((cv) => (
                    <option key={cv.id} value={cv.vehicle_id}>
                      {cv.vehicle ? `${cv.vehicle.brand} ${cv.vehicle.model}` : `Vehicle #${cv.vehicle_id}`}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="cf-label" htmlFor="proof">
                  File
                </label>
                <input id="proof" name="proof" type="file" accept="image/*,.pdf" className="block text-sm" required />
              </div>
              <button type="submit" className="cf-btn" disabled={busy}>
                Upload
              </button>
            </form>
            {proofMsg ? <p className="mt-2 text-sm text-muted-foreground">{proofMsg}</p> : null}
          </div>
        </>
      ) : null}
    </AdvertiserShell>
  );
}
