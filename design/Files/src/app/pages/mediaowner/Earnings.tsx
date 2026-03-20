import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { apiJson } from '@/lib/api';

type Row = { vehicle_id: number; total: string };

export function MediaOwnerEarnings() {
  const [total, setTotal] = useState<string>('0');
  const [byVehicle, setByVehicle] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    apiJson<{ total_agreed_price: string; by_vehicle: Row[] }>('/api/media-owner/earnings')
      .then((r) => {
        setTotal(r.total_agreed_price);
        setByVehicle(r.by_vehicle ?? []);
      })
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
      <h1 className="text-3xl mb-2">Earnings</h1>
      <p className="text-muted-foreground mb-6">Sum of agreed placement prices (API).</p>
      <div className="bg-card border border-border rounded-lg p-6 max-w-md mb-8">
        <p className="text-sm text-muted-foreground mb-1">Total agreed price</p>
        <p className="text-3xl font-semibold" style={{ color: '#C1F60D' }}>
          {total}
        </p>
      </div>
      {byVehicle.length === 0 ? (
        <p className="text-muted-foreground text-sm">No linked campaign vehicles yet.</p>
      ) : (
        <div className="border border-border rounded-lg overflow-hidden max-w-lg">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Vehicle ID</th>
                <th className="text-left p-3">Total</th>
              </tr>
            </thead>
            <tbody>
              {byVehicle.map((r) => (
                <tr key={r.vehicle_id} className="border-t border-border">
                  <td className="p-3 font-mono">{r.vehicle_id}</td>
                  <td className="p-3">{r.total}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
