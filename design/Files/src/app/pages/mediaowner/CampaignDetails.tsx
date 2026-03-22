import { FormEvent, useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { ArrowLeft, ExternalLink, Loader2, Upload } from 'lucide-react';
import { apiFormData, apiJson } from '@/lib/api';

type Pivot = { placement_size_class?: string; agreed_price?: string | null };
type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  color_label?: string | null;
  status_label?: string;
  pivot?: Pivot;
};
type Campaign = {
  id: number;
  name: string;
  status: string;
  description: string | null;
  advertiser?: { name: string; email: string };
  vehicles?: Vehicle[];
};

type ProofRow = {
  id: number;
  vehicle_id: number;
  status: string;
  comment: string | null;
  url: string;
  created_at: string | null;
  vehicle: { id: number; brand: string; model: string; imei: string } | null;
};

export function MediaOwnerCampaignDetails() {
  const { id } = useParams();
  const [c, setC] = useState<Campaign | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [proofVid, setProofVid] = useState('');
  const [proofMsg, setProofMsg] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [proofs, setProofs] = useState<ProofRow[]>([]);

  const loadProofs = useCallback(() => {
    if (!id) return;
    apiJson<{ data: ProofRow[] }>(`/api/media-owner/campaigns/${id}/proofs`)
      .then((r) => setProofs(r.data ?? []))
      .catch(() => setProofs([]));
  }, [id]);

  const load = useCallback(() => {
    if (!id) return;
    setLoading(true);
    setError(null);
    apiJson<Campaign>(`/api/media-owner/campaigns/${id}`)
      .then((data) => {
        setC(data);
        const vs = data.vehicles ?? [];
        if (vs.length) {
          setProofVid((prev) => prev || String(vs[0].id));
        }
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    loadProofs();
  }, [loadProofs]);

  async function onProofUpload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!id || !proofVid) return;
    const input = (e.target as HTMLFormElement).elements.namedItem('proof') as HTMLInputElement;
    const file = input?.files?.[0];
    if (!file) {
      setProofMsg('Choose a file.');
      return;
    }
    setProofMsg(null);
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('vehicle_id', proofVid);
      fd.append('file', file);
      await apiFormData(`/api/media-owner/campaigns/${id}/proofs`, fd);
      setProofMsg('Upload successful. Status: uploaded.');
      input.value = '';
      loadProofs();
    } catch (err) {
      setProofMsg(err instanceof Error ? err.message : 'Upload failed');
    } finally {
      setBusy(false);
    }
  }

  if (loading && !c) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin" />
      </div>
    );
  }

  if (error || !c) {
    return (
      <div className="p-8">
        <Link to="/mediaowner/campaigns" className="text-sm text-muted-foreground mb-4 inline-block">
          ← Back
        </Link>
        <p className="text-destructive">{error ?? 'Not found'}</p>
      </div>
    );
  }

  const vs = c.vehicles ?? [];

  return (
    <div className="p-8 max-w-5xl">
      <Link to="/mediaowner/campaigns" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6 hover:text-foreground">
        <ArrowLeft className="w-4 h-4" />
        Back
      </Link>
      <h1 className="text-3xl mb-2">{c.name}</h1>
      <p className="text-muted-foreground mb-2 capitalize">Status: {c.status}</p>
      {c.advertiser ? (
        <p className="text-sm text-muted-foreground mb-4">
          Advertiser: {c.advertiser.name} ({c.advertiser.email})
        </p>
      ) : null}
      {c.description ? <p className="mb-6 max-w-2xl text-sm">{c.description}</p> : null}

      <h2 className="text-xl mb-3">Your vehicles on this campaign</h2>
      {vs.length === 0 ? (
        <p className="text-muted-foreground text-sm mb-8">No vehicles linked for your fleet.</p>
      ) : (
        <div className="border border-border rounded-lg overflow-hidden max-w-4xl mb-8">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="text-left p-3">Vehicle</th>
                <th className="text-left p-3">IMEI</th>
                <th className="text-left p-3">Color</th>
                <th className="text-left p-3">Fleet</th>
                <th className="text-left p-3">Placement</th>
                <th className="text-left p-3">Agreed</th>
              </tr>
            </thead>
            <tbody>
              {vs.map((v) => (
                <tr key={v.id} className="border-t border-border">
                  <td className="p-3">
                    {v.brand} {v.model}
                  </td>
                  <td className="p-3 font-mono text-xs">{v.imei}</td>
                  <td className="p-3">{v.color_label ?? '—'}</td>
                  <td className="p-3 text-xs">{v.status_label ?? '—'}</td>
                  <td className="p-3">{v.pivot?.placement_size_class ?? '—'}</td>
                  <td className="p-3">{v.pivot?.agreed_price ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {vs.length > 0 ? (
        <div className="rounded-lg border border-border bg-card p-5 max-w-2xl">
          <h2 className="text-lg font-semibold mb-2 flex items-center gap-2">
            <Upload className="w-5 h-5" />
            Upload campaign proof
          </h2>
          <p className="text-xs text-muted-foreground mb-4">Your vehicle on this campaign — image or PDF.</p>
          <form className="flex flex-wrap items-end gap-3" onSubmit={(ev) => void onProofUpload(ev)}>
            <div>
              <label className="block text-xs font-medium text-muted-foreground mb-1">Vehicle</label>
              <select
                value={proofVid}
                onChange={(e) => setProofVid(e.target.value)}
                required
                className="px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input min-w-[200px] text-sm"
              >
                {vs.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.brand} {v.model} — {v.imei}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-muted-foreground mb-1">File</label>
              <input name="proof" type="file" accept="image/*,.pdf" required className="block text-sm" />
            </div>
            <button type="submit" disabled={busy} className="px-4 py-2 rounded-md bg-primary text-primary-foreground text-sm font-medium disabled:opacity-50">
              Upload
            </button>
          </form>
          {proofMsg ? <p className="mt-3 text-sm text-muted-foreground">{proofMsg}</p> : null}
        </div>
      ) : null}

      <div className="mt-8 rounded-lg border border-border bg-card p-5 max-w-4xl">
        <h2 className="text-lg font-semibold mb-3">Your proofs on this campaign</h2>
        {proofs.length === 0 ? (
          <p className="text-sm text-muted-foreground">No proofs uploaded for your vehicles yet.</p>
        ) : (
          <div className="border border-border rounded-lg overflow-x-auto">
            <table className="w-full text-sm min-w-[480px]">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-3">Vehicle</th>
                  <th className="text-left p-3">Status</th>
                  <th className="text-left p-3">Uploaded</th>
                  <th className="text-left p-3">File</th>
                </tr>
              </thead>
              <tbody>
                {proofs.map((p) => (
                  <tr key={p.id} className="border-t border-border">
                    <td className="p-3">
                      {p.vehicle ? `${p.vehicle.brand} ${p.vehicle.model}` : `#${p.vehicle_id}`}
                    </td>
                    <td className="p-3 capitalize">{p.status}</td>
                    <td className="p-3 text-muted-foreground">
                      {p.created_at ? new Date(p.created_at).toLocaleString() : '—'}
                    </td>
                    <td className="p-3">
                      <a
                        href={p.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 text-primary hover:underline"
                      >
                        Open <ExternalLink className="w-3.5 h-3.5" />
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
