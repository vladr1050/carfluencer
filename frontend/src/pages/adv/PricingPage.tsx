import { useEffect, useState } from 'react';
import { apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type PolicyRow = {
  id: number;
  size_class: string;
  base_price: string;
  currency: string;
  description: string | null;
};

export default function AdvPricingPage(): JSX.Element {
  const [state, setState] = useState<ApiState<PolicyRow[]>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<{ data: PolicyRow[] }>('/api/advertiser/pricing')
      .then((res) => {
        const rows = res.data ?? [];
        setState(rows.length ? { status: 'success', data: rows } : { status: 'empty' });
      })
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <AdvertiserShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Ad placement pricing</h1>
      <p className="mb-8 text-sm text-muted-foreground">
        <strong>S / M / L / XL</strong> = ad <strong>placement</strong> size on a vehicle, not vehicle category.
      </p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'empty' ? <p className="text-muted-foreground">No active policies.</p> : null}
      {state.status === 'success' ? (
        <div className="cf-card overflow-x-auto p-0">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Size</th>
                <th className="px-4 py-3">Base price</th>
                <th className="px-4 py-3">Description</th>
              </tr>
            </thead>
            <tbody>
              {state.data.map((row) => (
                <tr key={row.id} className="border-b border-border">
                  <td className="px-4 py-3 font-bold text-brand-magenta">{row.size_class}</td>
                  <td className="px-4 py-3">
                    {row.base_price} {row.currency}
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{row.description ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
