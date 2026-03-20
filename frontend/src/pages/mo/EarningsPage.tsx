import { useEffect, useState } from 'react';
import { apiJson, type ApiState } from '../../api/client';
import { MediaOwnerShell } from '../../layouts/MediaOwnerShell';

type EarningsRow = { vehicle_id: number; total: string };
type EarningsPayload = {
  total_agreed_price: string;
  by_vehicle: EarningsRow[];
};

export default function MoEarningsPage(): JSX.Element {
  const [state, setState] = useState<ApiState<EarningsPayload>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<EarningsPayload>('/api/media-owner/earnings')
      .then((data) => setState({ status: 'success', data }))
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <MediaOwnerShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Earnings</h1>
      <p className="mb-8 text-sm text-muted-foreground">Totals from agreed placement prices on your linked vehicles.</p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'success' ? (
        <>
          <div className="mb-6 cf-card">
            <p className="text-xs font-medium uppercase text-muted-foreground">Total agreed price</p>
            <p className="mt-2 text-3xl font-bold text-foreground">{state.data.total_agreed_price}</p>
          </div>
          {!state.data.by_vehicle.length ? (
            <p className="text-sm text-muted-foreground">No linked campaign vehicles yet.</p>
          ) : (
            <div className="cf-card overflow-x-auto p-0">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3">Vehicle ID</th>
                    <th className="px-4 py-3">Sum</th>
                  </tr>
                </thead>
                <tbody>
                  {state.data.by_vehicle.map((row) => (
                    <tr key={row.vehicle_id} className="border-b border-border">
                      <td className="px-4 py-3 font-mono">{row.vehicle_id}</td>
                      <td className="px-4 py-3">{row.total}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      ) : null}
    </MediaOwnerShell>
  );
}
