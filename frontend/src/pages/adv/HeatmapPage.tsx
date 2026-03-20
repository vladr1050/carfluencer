import { FormEvent, useEffect, useState } from 'react';
import { apiJson, type ApiState } from '../../api/client';
import { HeatmapMap } from '../../components/HeatmapMap';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type Campaign = { id: number; name: string };

type HeatmapResponse = {
  campaign: { id: number; name: string };
  heatmap: {
    points: { lat: number; lng: number; intensity: number }[];
    metrics: Record<string, unknown>;
  };
};

export default function AdvHeatmapPage(): JSX.Element {
  const [campaignsState, setCampaignsState] = useState<ApiState<Campaign[]>>({ status: 'idle' });
  const [heatmapState, setHeatmapState] = useState<ApiState<HeatmapResponse>>({ status: 'idle' });
  const [showRawJson, setShowRawJson] = useState(false);

  const [campaignId, setCampaignId] = useState('');
  const [vehicleId, setVehicleId] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [mode, setMode] = useState<'driving' | 'parking' | 'both'>('both');

  useEffect(() => {
    setCampaignsState({ status: 'loading' });
    apiJson<{ data: Campaign[] }>('/api/advertiser/campaigns')
      .then((res) => {
        const rows = res.data ?? [];
        setCampaignsState(rows.length ? { status: 'success', data: rows } : { status: 'empty' });
        if (rows.length) {
          setCampaignId((prev) => prev || String(rows[0].id));
        }
      })
      .catch((e: Error) => setCampaignsState({ status: 'error', message: e.message }));
  }, []);

  async function onLoad(e?: FormEvent): Promise<void> {
    e?.preventDefault();
    if (!campaignId) {
      setHeatmapState({ status: 'error', message: 'Select a campaign.' });
      return;
    }
    setHeatmapState({ status: 'loading' });
    const params = new URLSearchParams({ campaign_id: campaignId, mode });
    if (vehicleId) {
      params.set('vehicle_id', vehicleId);
    }
    if (dateFrom) {
      params.set('date_from', dateFrom);
    }
    if (dateTo) {
      params.set('date_to', dateTo);
    }
    try {
      const data = await apiJson<HeatmapResponse>(`/api/advertiser/heatmap?${params.toString()}`);
      const empty = !data.heatmap?.points?.length;
      setHeatmapState(empty ? { status: 'empty' } : { status: 'success', data });
    } catch (err) {
      setHeatmapState({
        status: 'error',
        message: err instanceof Error ? err.message : 'Failed to load heatmap',
      });
    }
  }

  return (
    <AdvertiserShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Heatmap</h1>
      <p className="mb-8 text-sm text-muted-foreground">OpenStreetMap + telemetry adapter (mock until production API).</p>

      {campaignsState.status === 'loading' ? <p className="text-muted-foreground">Loading campaigns…</p> : null}
      {campaignsState.status === 'error' ? <p className="text-red-600">{campaignsState.message}</p> : null}
      {campaignsState.status === 'empty' ? <p className="text-muted-foreground">Create a campaign first.</p> : null}

      {campaignsState.status === 'success' ? (
        <form className="mb-8 cf-card" onSubmit={(e) => void onLoad(e)}>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className="cf-label">Campaign</label>
              <select className="cf-input" value={campaignId} onChange={(e) => setCampaignId(e.target.value)} required>
                {campaignsState.data.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} (#{c.id})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="cf-label">Vehicle ID (optional)</label>
              <input className="cf-input" value={vehicleId} onChange={(e) => setVehicleId(e.target.value)} placeholder="e.g. 1" />
            </div>
            <div>
              <label className="cf-label">Date from</label>
              <input className="cf-input" type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
            </div>
            <div>
              <label className="cf-label">Date to</label>
              <input className="cf-input" type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
            </div>
            <div>
              <label className="cf-label">Mode</label>
              <select className="cf-input" value={mode} onChange={(e) => setMode(e.target.value as typeof mode)}>
                <option value="driving">Driving</option>
                <option value="parking">Parking</option>
                <option value="both">Both</option>
              </select>
            </div>
            <div className="flex items-end">
              <button className="cf-btn w-full sm:w-auto" type="submit">
                Load heatmap
              </button>
            </div>
          </div>
        </form>
      ) : null}

      {heatmapState.status === 'loading' ? <p className="text-muted-foreground">Loading heatmap…</p> : null}
      {heatmapState.status === 'error' ? <p className="text-red-600">{heatmapState.message}</p> : null}
      {heatmapState.status === 'empty' ? <p className="text-muted-foreground">No points for these filters.</p> : null}
      {heatmapState.status === 'success' ? (
        <div className="cf-card space-y-4">
          <h2 className="text-lg font-semibold text-foreground">{heatmapState.data.campaign.name}</h2>
          <HeatmapMap points={heatmapState.data.heatmap.points} />
          {heatmapState.data.heatmap.metrics ? (
            <div>
              <h3 className="mb-2 text-sm font-semibold text-foreground">Metrics</h3>
              <ul className="list-inside list-disc text-sm text-muted-foreground">
                {Object.entries(heatmapState.data.heatmap.metrics).map(([k, v]) => (
                  <li key={k}>
                    <code className="text-xs">{k}</code>: {String(v)}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          <button type="button" className="cf-btn-secondary" onClick={() => setShowRawJson((s) => !s)}>
            {showRawJson ? 'Hide' : 'Show'} raw JSON
          </button>
          {showRawJson ? (
            <pre className="max-h-64 overflow-auto rounded-xl border border-border bg-black p-4 font-mono text-xs text-brand-lime">
              {JSON.stringify(heatmapState.data.heatmap, null, 2)}
            </pre>
          ) : null}
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
