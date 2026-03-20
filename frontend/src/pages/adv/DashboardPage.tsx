import { useEffect, useState } from 'react';
import { apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type Dashboard = {
  active_campaigns_count: number;
  impressions: number;
  driving_distance_km: number;
  driving_time_hours: number;
  parking_time_hours: number;
  note?: string;
  source?: string;
};

export default function AdvDashboardPage(): JSX.Element {
  const [state, setState] = useState<ApiState<Dashboard>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<Dashboard>('/api/advertiser/dashboard')
      .then((data) => setState({ status: 'success', data }))
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <AdvertiserShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Dashboard</h1>
      <p className="mb-8 text-sm text-muted-foreground">Campaign overview and telemetry aggregates.</p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'success' ? (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
              Metrics source: {state.data.source ?? '—'}
            </span>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="cf-card">
              <p className="text-xs font-medium uppercase text-muted-foreground">Active campaigns</p>
              <p className="mt-2 text-3xl font-bold text-foreground">{state.data.active_campaigns_count}</p>
            </div>
            <div className="cf-card">
              <p className="text-xs font-medium uppercase text-muted-foreground">Impressions</p>
              <p className="mt-2 text-3xl font-bold text-foreground">{Number(state.data.impressions).toLocaleString()}</p>
            </div>
            <div className="cf-card">
              <p className="text-xs font-medium uppercase text-muted-foreground">Driving km</p>
              <p className="mt-2 text-3xl font-bold text-foreground">{Number(state.data.driving_distance_km).toLocaleString()}</p>
            </div>
            <div className="cf-card">
              <p className="text-xs font-medium uppercase text-muted-foreground">Driving hours</p>
              <p className="mt-2 text-3xl font-bold text-foreground">{Number(state.data.driving_time_hours).toLocaleString()}</p>
            </div>
            <div className="cf-card">
              <p className="text-xs font-medium uppercase text-muted-foreground">Parking hours</p>
              <p className="mt-2 text-3xl font-bold text-foreground">{Number(state.data.parking_time_hours).toLocaleString()}</p>
            </div>
          </div>
          {state.data.note ? <p className="text-sm text-muted-foreground">{state.data.note}</p> : null}
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
