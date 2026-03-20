import { FormEvent, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiJson, type ApiState } from '../../api/client';
import { AdvertiserShell } from '../../layouts/AdvertiserShell';

type Campaign = {
  id: number;
  name: string;
  status: string;
};

export default function AdvCampaignsPage(): JSX.Element {
  const [listState, setListState] = useState<ApiState<Campaign[]>>({ status: 'idle' });
  const [name, setName] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function load(): void {
    setListState({ status: 'loading' });
    apiJson<{ data: Campaign[] }>('/api/advertiser/campaigns')
      .then((res) => {
        const rows = res.data ?? [];
        setListState(rows.length ? { status: 'success', data: rows } : { status: 'empty' });
      })
      .catch((e: Error) => setListState({ status: 'error', message: e.message }));
  }

  useEffect(() => {
    load();
  }, []);

  async function onCreate(e: FormEvent): Promise<void> {
    e.preventDefault();
    setFormError(null);
    setSaving(true);
    try {
      await apiJson('/api/advertiser/campaigns', {
        method: 'POST',
        body: JSON.stringify({ name }),
      });
      setName('');
      load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Failed');
    } finally {
      setSaving(false);
    }
  }

  return (
    <AdvertiserShell>
      <h1 className="mb-2 text-2xl font-bold text-foreground">Campaigns</h1>
      <p className="mb-8 text-sm text-muted-foreground">Create campaigns and open a record to attach vehicles and upload proofs.</p>

      <div className="mb-8 cf-card">
        <h2 className="mb-4 text-base font-semibold text-foreground">New campaign</h2>
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => void onCreate(e)}>
          <div className="min-w-[200px] flex-1">
            <label className="cf-label" htmlFor="cname">
              Name
            </label>
            <input id="cname" className="cf-input" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <button className="cf-btn" type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Create'}
          </button>
        </form>
        {formError ? <p className="mt-2 text-sm text-red-600">{formError}</p> : null}
      </div>

      {listState.status === 'loading' ? <p className="text-muted-foreground">Loading…</p> : null}
      {listState.status === 'error' ? <p className="text-red-600">{listState.message}</p> : null}
      {listState.status === 'empty' ? <p className="text-muted-foreground">No campaigns yet.</p> : null}
      {listState.status === 'success' ? (
        <div className="cf-card overflow-x-auto p-0">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-muted text-xs uppercase text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {listState.data.map((c) => (
                <tr key={c.id} className="border-b border-border">
                  <td className="px-4 py-3 font-medium">{c.name}</td>
                  <td className="px-4 py-3">{c.status}</td>
                  <td className="px-4 py-3 text-right">
                    <Link className="text-sm font-semibold text-brand-magenta hover:underline" to={`/advertiser/campaigns/${c.id}`}>
                      Manage
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </AdvertiserShell>
  );
}
