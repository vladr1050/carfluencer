import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type VehicleDetail = {
  id: number;
  brand: string;
  model: string;
  year: number | null;
  imei: string;
  status: string;
  color: string | null;
  media_owner?: { name: string; company_name: string | null };
};

export default function AdvVehicleDetailPage(): JSX.Element {
  const { vehicleId } = useParams<{ vehicleId: string }>();
  const [state, setState] = useState<ApiState<VehicleDetail>>({ status: 'idle' });

  useEffect(() => {
    if (!vehicleId) {
      return;
    }
    setState({ status: 'loading' });
    apiJson<VehicleDetail>(`/api/advertiser/vehicles/${vehicleId}`)
      .then((data) => setState({ status: 'success', data }))
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, [vehicleId]);

  return (
    <AdvertiserShell>
      <Link to="/advertiser/vehicles" className="mb-4 inline-block text-sm font-medium text-muted-foreground hover:text-foreground">
        ← Catalog
      </Link>
      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'success' ? (
        <div className="cf-card space-y-2">
          <h1 className="text-2xl font-bold text-foreground">
            {state.data.brand} {state.data.model}
          </h1>
          <p className="text-sm text-muted-foreground">
            IMEI: <span className="font-mono">{state.data.imei}</span>
          </p>
          <p className="text-sm text-muted-foreground">Year: {state.data.year ?? '—'}</p>
          <p className="text-sm text-muted-foreground">Color: {state.data.color ?? '—'}</p>
          <p className="text-sm text-muted-foreground">Status: {state.data.status}</p>
          {state.data.media_owner ? (
            <p className="text-sm text-muted-foreground">
              Media owner: {state.data.media_owner.name}
              {state.data.media_owner.company_name ? ` (${state.data.media_owner.company_name})` : ''}
            </p>
          ) : null}
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
