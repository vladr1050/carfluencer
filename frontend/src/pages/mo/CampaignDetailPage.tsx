import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { apiJson, type ApiState } from '../../api/client';
import { MediaOwnerShell } from '../../layouts/MediaOwnerShell';

type Pivot = { placement_size_class?: string; agreed_price?: string | null; status?: string };
type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  pivot?: Pivot;
};
type CampaignDetail = {
  id: number;
  name: string;
  status: string;
  description: string | null;
  advertiser?: { name: string; email: string };
  vehicles?: Vehicle[];
};

export default function MoCampaignDetailPage(): JSX.Element {
  const { campaignId } = useParams<{ campaignId: string }>();
  const [state, setState] = useState<ApiState<CampaignDetail>>({ status: 'idle' });

  useEffect(() => {
    if (!campaignId) {
      return;
    }
    setState({ status: 'loading' });
    apiJson<CampaignDetail>(`/api/media-owner/campaigns/${campaignId}`)
      .then((data) => setState({ status: 'success', data }))
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, [campaignId]);

  return (
    <MediaOwnerShell>
      <Link to="/media-owner/campaigns" className="mb-4 inline-block text-sm font-medium text-muted-foreground hover:text-foreground">
        ← Back to campaigns
      </Link>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'success' ? (
        <>
          <h1 className="mb-2 text-2xl font-bold text-foreground">{state.data.name}</h1>
          <p className="mb-6 text-sm text-muted-foreground">
            Status: <span className="font-medium text-foreground">{state.data.status}</span>
            {state.data.advertiser ? (
              <>
                {' '}
                · Advertiser: {state.data.advertiser.name} ({state.data.advertiser.email})
              </>
            ) : null}
          </p>
          {state.data.description ? <p className="mb-6 text-sm text-muted-foreground">{state.data.description}</p> : null}

          <h2 className="mb-3 text-lg font-semibold text-foreground">Your vehicles on this campaign</h2>
          {!state.data.vehicles?.length ? (
            <p className="text-sm text-muted-foreground">No vehicles linked for your fleet on this campaign.</p>
          ) : (
            <div className="cf-card overflow-x-auto p-0">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3">Vehicle</th>
                    <th className="px-4 py-3">IMEI</th>
                    <th className="px-4 py-3">Placement</th>
                    <th className="px-4 py-3">Agreed price</th>
                  </tr>
                </thead>
                <tbody>
                  {state.data.vehicles.map((v) => (
                    <tr key={v.id} className="border-b border-border">
                      <td className="px-4 py-3 font-medium">
                        {v.brand} {v.model}
                      </td>
                      <td className="px-4 py-3 font-mono text-xs">{v.imei}</td>
                      <td className="px-4 py-3">{v.pivot?.placement_size_class ?? '—'}</td>
                      <td className="px-4 py-3">{v.pivot?.agreed_price ?? '—'}</td>
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
