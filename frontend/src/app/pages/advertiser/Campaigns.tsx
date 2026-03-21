import { useCallback, useEffect, useState } from 'react';
import { Calendar, Car, MapPin, Eye, Loader2, AlertCircle } from 'lucide-react';
import { Link } from 'react-router-dom';
import { apiJson } from '@/lib/api';

type ApiCampaign = {
  id: number;
  name: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  campaign_vehicles?: unknown[];
};

export function AdvertiserCampaigns() {
  const [campaigns, setCampaigns] = useState<ApiCampaign[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    apiJson<{ data: ApiCampaign[] }>('/api/advertiser/campaigns')
      .then((res) => setCampaigns(res.data ?? []))
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function onCreate(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await apiJson('/api/advertiser/campaigns', {
        method: 'POST',
        body: JSON.stringify({ name }),
      });
      setName('');
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Create failed');
    } finally {
      setSaving(false);
    }
  }

  if (loading && campaigns.length === 0) {
    return (
      <div className="p-8 flex flex-col items-center justify-center min-h-[50vh] gap-4">
        <Loader2 className="w-10 h-10 animate-spin text-primary" />
        <p className="text-muted-foreground">Loading campaigns…</p>
      </div>
    );
  }

  if (error && campaigns.length === 0) {
    return (
      <div className="p-8">
        <div className="bg-card border border-border rounded-lg p-8 max-w-md">
          <AlertCircle className="w-12 h-12 mb-4 text-destructive" />
          <p className="text-destructive mb-4">{error}</p>
          <button type="button" onClick={() => load()} className="px-4 py-2 bg-primary text-primary-foreground rounded-lg">
            Retry
          </button>
        </div>
      </div>
    );
  }

  const fmt = (d: string | null) =>
    d
      ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
      : '—';

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-3xl mb-2">Campaigns</h1>
        <p className="text-muted-foreground">Data from Laravel API.</p>
      </div>

      <form onSubmit={(e) => void onCreate(e)} className="mb-8 flex flex-wrap gap-3 items-end bg-card border border-border rounded-lg p-4">
        <div className="flex-1 min-w-[200px]">
          <label className="block text-sm mb-1 text-muted-foreground">New campaign name</label>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full px-3 py-2 rounded-md bg-input-background dark:bg-input border border-border"
            placeholder="e.g. Spring launch"
            required
          />
        </div>
        <button type="submit" disabled={saving} className="px-4 py-2 bg-primary text-primary-foreground rounded-lg disabled:opacity-50">
          {saving ? 'Saving…' : 'Create'}
        </button>
      </form>

      {campaigns.length === 0 ? (
        <p className="text-muted-foreground">No campaigns yet. Create one above.</p>
      ) : (
        <div className="space-y-6">
          {campaigns.map((campaign) => {
            const n = campaign.campaign_vehicles?.length ?? 0;
            const start = campaign.start_date ?? '';
            const end = campaign.end_date ?? '';
            return (
              <div key={campaign.id} className="bg-card border border-border rounded-lg p-6 hover:shadow-lg transition-shadow">
                <div className="flex items-start justify-between mb-6 flex-wrap gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2 flex-wrap">
                      <Link to={`/advertiser/campaigns/${campaign.id}`} className="hover:text-primary transition-colors">
                        <h3 className="text-xl">{campaign.name}</h3>
                      </Link>
                      <span className="px-3 py-1 rounded-full text-xs bg-muted capitalize">{campaign.status}</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Calendar className="w-4 h-4" />
                      <span>
                        {fmt(campaign.start_date)} — {fmt(campaign.end_date)}
                      </span>
                    </div>
                  </div>
                  <Link
                    to={`/advertiser/heatmap?campaignId=${campaign.id}&campaignName=${encodeURIComponent(campaign.name)}${start ? `&dateFrom=${start}` : ''}${end ? `&dateTo=${end}` : ''}&vehicle=all`}
                    className="px-4 py-2 bg-accent text-accent-foreground rounded-lg text-sm hover:opacity-90 flex items-center gap-2"
                  >
                    <MapPin className="w-4 h-4" />
                    View Heatmap
                  </Link>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-muted rounded-lg">
                    <div className="flex items-center gap-2 mb-2">
                      <Car className="w-4 h-4 text-muted-foreground" />
                      <span className="text-xs text-muted-foreground">Vehicles on campaign</span>
                    </div>
                    <div className="text-xl">{n}</div>
                  </div>
                  <div className="p-4 bg-muted rounded-lg">
                    <div className="flex items-center gap-2 mb-2">
                      <Eye className="w-4 h-4 text-muted-foreground" />
                      <span className="text-xs text-muted-foreground">Catalog</span>
                    </div>
                    <div className="text-sm text-muted-foreground">See heatmap for exposure metrics</div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
