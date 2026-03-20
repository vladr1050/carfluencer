import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  status: string;
};

export default function AdvVehiclesPage(): JSX.Element {
  const [state, setState] = useState<ApiState<Vehicle[]>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<{ data: Vehicle[] }>('/api/advertiser/vehicles?per_page=200')
      .then((res) => {
        const rows = res.data ?? [];
        setState(rows.length ? { status: 'success', data: rows } : { status: 'empty' });
      })
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <AdvertiserShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Vehicle catalog</h1>
      <p className="mb-8 text-sm text-muted-foreground">Active inventory you can attach to your campaigns.</p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'empty' ? <p className="text-muted-foreground">No vehicles in catalog.</p> : null}
      {state.status === 'success' ? (
        <div className="cf-card overflow-x-auto p-0">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Brand / model</th>
                <th className="px-4 py-3">IMEI</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {state.data.map((v) => (
                <tr key={v.id} className="border-b border-border">
                  <td className="px-4 py-3 font-medium">
                    {v.brand} {v.model}
                  </td>
                  <td className="px-4 py-3 font-mono text-xs">{v.imei}</td>
                  <td className="px-4 py-3 text-right">
                    <Link to={`/advertiser/vehicles/${v.id}`} className="text-sm font-semibold text-brand-magenta hover:underline">
                      Details
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
