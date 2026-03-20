import { useEffect, useState } from 'react';
import { Briefcase, Eye, Navigation, Clock, ParkingCircle, Loader2 } from 'lucide-react';
import { Link } from 'react-router';
import { apiJson } from '@/lib/api';

type DashboardPayload = {
  active_campaigns_count: number;
  impressions: number;
  driving_distance_km: number;
  driving_time_hours: number;
  parking_time_hours: number;
  note?: string;
  source?: string;
};

type CampaignRow = {
  id: number;
  name: string;
  status: string;
  campaign_vehicles?: unknown[];
};

export function AdvertiserDashboard() {
  const [dash, setDash] = useState<DashboardPayload | null>(null);
  const [campaigns, setCampaigns] = useState<CampaignRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    Promise.all([
      apiJson<DashboardPayload>('/api/advertiser/dashboard'),
      apiJson<{ data: CampaignRow[] }>('/api/advertiser/campaigns'),
    ])
      .then(([d, c]) => {
        if (!cancelled) {
          setDash(d);
          setCampaigns(c.data ?? []);
        }
      })
      .catch((e: Error) => {
        if (!cancelled) setError(e.message);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (loading) {
    return (
      <div className="p-8 flex flex-col items-center justify-center min-h-[50vh] gap-4">
        <Loader2 className="w-10 h-10 animate-spin text-primary" />
        <p className="text-muted-foreground">Loading dashboard…</p>
      </div>
    );
  }

  if (error || !dash) {
    return (
      <div className="p-8">
        <p className="text-destructive">{error ?? 'Failed to load'}</p>
      </div>
    );
  }

  const stats = [
    { label: 'Active campaigns', value: String(dash.active_campaigns_count), icon: Briefcase, color: '#F10DBF' },
    { label: 'Impressions', value: Number(dash.impressions).toLocaleString(), icon: Eye, color: '#C1F60D' },
    { label: 'Driving distance', value: `${Number(dash.driving_distance_km).toLocaleString()} km`, icon: Navigation, color: '#F10DBF' },
    { label: 'Driving time', value: `${Number(dash.driving_time_hours).toLocaleString()} hrs`, icon: Clock, color: '#545454' },
    { label: 'Parking time', value: `${Number(dash.parking_time_hours).toLocaleString()} hrs`, icon: ParkingCircle, color: '#F10DBF' },
  ];

  const activeList = campaigns.filter((c) => c.status === 'active').slice(0, 6);

  return (
    <div className="p-8">
      <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl mb-2">Dashboard</h1>
          <p className="text-muted-foreground">Live metrics from the Laravel API.</p>
        </div>
        <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
          Source: {dash.source ?? '—'}
        </span>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        {stats.map((stat) => {
          const Icon = stat.icon;
          return (
            <div key={stat.label} className="bg-card border border-border rounded-lg p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center justify-between mb-4">
                <div className="p-2 rounded-lg bg-muted">
                  <Icon className="w-6 h-6" style={{ color: stat.color }} />
                </div>
              </div>
              <div className="text-2xl mb-1">{stat.value}</div>
              <div className="text-sm text-muted-foreground">{stat.label}</div>
            </div>
          );
        })}
      </div>

      {dash.note ? <p className="mb-6 text-sm text-muted-foreground">{dash.note}</p> : null}

      <div className="bg-card border border-border rounded-lg p-6">
        <h3 className="mb-4">Campaigns</h3>
        {activeList.length === 0 ? (
          <p className="text-muted-foreground text-sm">No active campaigns. Create one under Campaigns.</p>
        ) : (
          <ul className="space-y-3">
            {activeList.map((c) => (
              <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 p-3 bg-muted rounded-lg">
                <Link to={`/advertiser/campaigns/${c.id}`} className="font-medium hover:text-primary">
                  {c.name}
                </Link>
                <span className="text-xs text-muted-foreground">
                  {(c.campaign_vehicles?.length ?? 0)} vehicles linked
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
