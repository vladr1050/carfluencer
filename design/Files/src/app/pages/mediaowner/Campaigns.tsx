import { useEffect, useState } from 'react';
import { Link } from 'react-router';
import { Loader2 } from 'lucide-react';
import { apiJson } from '@/lib/api';

type Row = {
  id: number;
  name: string;
  status: string;
  advertiser?: { name: string; email: string };
};

export function MediaOwnerCampaigns() {
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    apiJson<{ data: Row[] }>('/api/media-owner/campaigns')
      .then((r) => setRows(r.data ?? []))
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
      <h1 className="text-3xl mb-2">Campaigns</h1>
      <p className="text-muted-foreground mb-6">Campaigns where your fleet participates.</p>
      {rows.length === 0 ? (
        <p className="text-muted-foreground">No campaigns yet.</p>
      ) : (
        <div className="border border-border rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Name</th>
                <th className="text-left p-3">Status</th>
                <th className="text-left p-3">Advertiser</th>
                <th className="p-3" />
              </tr>
            </thead>
            <tbody>
              {rows.map((c) => (
                <tr key={c.id} className="border-t border-border">
                  <td className="p-3 font-medium">{c.name}</td>
                  <td className="p-3 capitalize">{c.status}</td>
                  <td className="p-3 text-muted-foreground">{c.advertiser?.name ?? '—'}</td>
                  <td className="p-3 text-right">
                    <Link to={`/mediaowner/campaigns/${c.id}`} className="text-primary text-sm font-medium hover:underline">
                      Open
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
