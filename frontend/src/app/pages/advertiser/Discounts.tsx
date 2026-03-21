import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { apiJson } from '@/lib/api';

type DiscountsPayload = {
  profile_discount_percent: string | null;
  agency_commission_percent: string | null;
};

export function AdvertiserDiscounts() {
  const [data, setData] = useState<DiscountsPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    apiJson<DiscountsPayload>('/api/advertiser/profile-discounts')
      .then(setData)
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

  if (error || !data) {
    return (
      <div className="p-8">
        <p className="text-destructive">{error ?? 'Error'}</p>
      </div>
    );
  }

  return (
    <div className="p-8">
      <h1 className="text-3xl mb-2">Discounts &amp; commissions</h1>
      <p className="text-muted-foreground mb-8 text-sm">Managed by platform admin on your profile.</p>
      <div className="grid gap-4 sm:grid-cols-2 max-w-2xl">
        <div className="rounded-lg border border-border bg-card p-6">
          <p className="text-xs font-medium uppercase text-muted-foreground">Profile discount</p>
          <p className="mt-2 text-3xl font-semibold">
            {data.profile_discount_percent != null ? `${data.profile_discount_percent}%` : '—'}
          </p>
        </div>
        <div className="rounded-lg border border-border bg-card p-6">
          <p className="text-xs font-medium uppercase text-muted-foreground">Agency commission</p>
          <p className="mt-2 text-3xl font-semibold">
            {data.agency_commission_percent != null ? `${data.agency_commission_percent}%` : '—'}
          </p>
        </div>
      </div>
    </div>
  );
}
