import { useEffect, useState } from 'react';
import { Car, Briefcase, Loader2 } from 'lucide-react';
import { apiJson } from '@/lib/api';

type Dash = {
  vehicles_count: number;
  active_campaign_participations: number;
};

export function MediaOwnerDashboard() {
  const [data, setData] = useState<Dash | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    apiJson<Dash>('/api/media-owner/dashboard')
      .then(setData)
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
        <span className="text-muted-foreground">Loading…</span>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="p-8">
        <p className="text-destructive">{error ?? 'Error'}</p>
      </div>
    );
  }

  return (
    <div className="p-8">
      <h1 className="text-3xl mb-2">Dashboard</h1>
      <p className="text-muted-foreground mb-8">Live data from Laravel.</p>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl">
        <div className="bg-card border border-border rounded-lg p-6">
          <div className="flex items-center gap-3 mb-2">
            <Car className="w-8 h-8" style={{ color: '#C1F60D' }} />
            <span className="text-sm text-muted-foreground">Vehicles</span>
          </div>
          <div className="text-4xl font-semibold">{data.vehicles_count}</div>
        </div>
        <div className="bg-card border border-border rounded-lg p-6">
          <div className="flex items-center gap-3 mb-2">
            <Briefcase className="w-8 h-8 text-accent" />
            <span className="text-sm text-muted-foreground">Active campaign links</span>
          </div>
          <div className="text-4xl font-semibold">{data.active_campaign_participations}</div>
        </div>
      </div>
    </div>
  );
}
