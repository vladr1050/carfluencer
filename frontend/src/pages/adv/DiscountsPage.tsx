import { useEffect, useState } from 'react';
import { apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type DiscountsPayload = {
  profile_discount_percent: string | null;
  agency_commission_percent: string | null;
};

export default function AdvDiscountsPage(): JSX.Element {
  const [state, setState] = useState<ApiState<DiscountsPayload>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<DiscountsPayload>('/api/advertiser/profile-discounts')
      .then((data) => setState({ status: 'success', data }))
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <AdvertiserShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Discounts &amp; commissions</h1>
      <p className="mb-8 text-sm text-muted-foreground">Set by platform admin on your advertiser profile.</p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'success' ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="cf-card">
            <p className="text-xs font-medium uppercase text-muted-foreground">Profile discount</p>
            <p className="mt-2 text-2xl font-bold text-foreground">
              {state.data.profile_discount_percent != null ? `${state.data.profile_discount_percent}%` : '—'}
            </p>
          </div>
          <div className="cf-card">
            <p className="text-xs font-medium uppercase text-muted-foreground">Agency commission (profile)</p>
            <p className="mt-2 text-2xl font-bold text-foreground">
              {state.data.agency_commission_percent != null ? `${state.data.agency_commission_percent}%` : '—'}
            </p>
          </div>
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
