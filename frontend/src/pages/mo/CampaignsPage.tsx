import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiJson, type ApiState } from '../../api/client';
import { MediaOwnerShell } from '../../layouts/MediaOwnerShell';

type Campaign = {
  id: number;
  name: string;
  status: string;
  advertiser?: { name: string; email: string };
};

export default function MoCampaignsPage(): JSX.Element {
  const [state, setState] = useState<ApiState<Campaign[]>>({ status: 'idle' });

  useEffect(() => {
    setState({ status: 'loading' });
    apiJson<{ data: Campaign[] }>('/api/media-owner/campaigns')
      .then((res) => {
        const rows = res.data ?? [];
        setState(rows.length ? { status: 'success', data: rows } : { status: 'empty' });
      })
      .catch((e: Error) => setState({ status: 'error', message: e.message }));
  }, []);

  return (
    <MediaOwnerShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Campaigns</h1>
      <p className="mb-8 text-sm text-muted-foreground">Campaigns where your vehicles participate.</p>

      {state.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {state.status === 'error' ? <p className="text-red-600">{state.message}</p> : null}
      {state.status === 'empty' ? <p className="text-muted-foreground">No campaigns yet.</p> : null}
      {state.status === 'success' ? (
        <div className="cf-card overflow-x-auto p-0">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Advertiser</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {state.data.map((c) => (
                <tr key={c.id} className="border-b border-border">
                  <td className="px-4 py-3 font-medium">{c.name}</td>
                  <td className="px-4 py-3">{c.status}</td>
                  <td className="px-4 py-3">{c.advertiser?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-right">
                    <Link className="text-sm font-semibold text-brand-magenta hover:underline" to={`/media-owner/campaigns/${c.id}`}>
                      Open
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </MediaOwnerShell>
  );
}
