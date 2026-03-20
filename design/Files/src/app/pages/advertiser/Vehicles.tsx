import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { Car, Loader2, Eye } from 'lucide-react';
import { apiJson } from '@/lib/api';

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  year: number | null;
  status: string;
};

export function AdvertiserVehicles() {
  const [rows, setRows] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    apiJson<{ data: Vehicle[] }>('/api/advertiser/vehicles?per_page=200')
      .then((r) => setRows(r.data ?? []))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="p-8 flex flex-col items-center justify-center min-h-[40vh] gap-3">
        <Loader2 className="w-10 h-10 animate-spin text-primary" />
        <p className="text-muted-foreground">Loading catalog…</p>
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
      <div className="mb-8">
        <h1 className="text-3xl mb-2">Vehicle catalog</h1>
        <p className="text-muted-foreground">Fleet you can attach to campaigns (read-only list from API).</p>
      </div>

      {rows.length === 0 ? (
        <p className="text-muted-foreground">No vehicles in catalog.</p>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {rows.map((v) => (
            <div key={v.id} className="bg-card border border-border rounded-lg p-5 flex flex-col gap-3">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h3 className="font-semibold text-lg">
                    {v.brand} {v.model}
                  </h3>
                  <p className="text-xs text-muted-foreground font-mono mt-1">{v.imei}</p>
                </div>
                <Car className="w-8 h-8 shrink-0 opacity-50" style={{ color: '#C1F60D' }} />
              </div>
              <p className="text-sm text-muted-foreground">
                Year: {v.year ?? '—'} · {v.status}
              </p>
              <Link
                to={`/advertiser/vehicles/${v.id}`}
                className="inline-flex items-center gap-2 text-sm text-primary hover:underline mt-auto"
              >
                <Eye className="w-4 h-4" />
                Details
              </Link>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
