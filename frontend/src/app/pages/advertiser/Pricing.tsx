import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { apiJson } from '@/lib/api';

type PolicyRow = {
  id: number;
  size_class: string;
  base_price: string;
  currency: string;
  description: string | null;
};

export function AdvertiserPricing() {
  const [rows, setRows] = useState<PolicyRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    apiJson<{ data: PolicyRow[] }>('/api/advertiser/pricing')
      .then((r) => setRows(r.data ?? []))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8">
        <p className="text-destructive">{error}</p>
      </div>
    );
  }

  return (
    <div className="p-8">
      <h1 className="text-3xl mb-2">Placement pricing</h1>
      <p className="text-muted-foreground mb-6 text-sm max-w-2xl">
        <strong>S / M / L / XL</strong> = ad <strong>placement</strong> size on the vehicle, not car category.
      </p>
      {rows.length === 0 ? (
        <p className="text-muted-foreground">No active policies.</p>
      ) : (
        <div className="border border-border rounded-lg overflow-hidden max-w-3xl">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Size</th>
                <th className="text-left p-3">Base price</th>
                <th className="text-left p-3">Description</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-t border-border">
                  <td className="p-3 font-bold" style={{ color: '#F10DBF' }}>
                    {row.size_class}
                  </td>
                  <td className="p-3">
                    {row.base_price} {row.currency}
                  </td>
                  <td className="p-3 text-muted-foreground">{row.description ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
