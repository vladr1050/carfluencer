import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiJson, type ApiState } from '../../api/client';
import { MediaOwnerShell } from '../../layouts/MediaOwnerShell';

type Dashboard = {
  vehicles_count: number;
  active_campaign_participations: number;
};

export default function MoDashboardPage(): JSX.Element {
  const [state, setState] = useState<ApiState<Dashboard>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<Dashboard>('/api/media-owner/dashboard')
      .then((data) => setState({ status: 'success', data }))
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <MediaOwnerShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Dashboard</h1>
      <p className="mb-8 text-sm text-muted-foreground">Overview of your fleet and active participations.</p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'success' ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="cf-card">
            <p className="text-xs font-medium uppercase text-muted-foreground">Vehicles</p>
            <p className="mt-2 text-3xl font-bold text-foreground">{state.data.vehicles_count}</p>
          </div>
          <div className="cf-card">
            <p className="text-xs font-medium uppercase text-muted-foreground">Active campaign links</p>
            <p className="mt-2 text-3xl font-bold text-foreground">{state.data.active_campaign_participations}</p>
          </div>
          <div className="sm:col-span-2">
            <Link className="cf-btn-secondary inline-flex" to="/media-owner/earnings">
              View earnings summary
            </Link>
          </div>
        </div>
      ) : null}
    </MediaOwnerShell>
  );
}
