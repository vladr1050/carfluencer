import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Car } from 'lucide-react';
import { apiJson } from '../api/client';
import { ContentBlockBody } from '../components/ContentBlockBody';
import { ThemeToggle } from '../components/ThemeToggle';

type ContentBlock = {
  id: number;
  key: string;
  title: string;
  body: string;
};

export default function HomePage(): JSX.Element {
  const [blocks, setBlocks] = useState<ContentBlock[] | null>(null);
  const [blocksError, setBlocksError] = useState<string | null>(null);

  useEffect(() => {
    apiJson<{ data: ContentBlock[] }>('/api/content-blocks')
      .then((res) => setBlocks(res.data))
      .catch((e: unknown) =>
        setBlocksError(e instanceof Error ? e.message : 'Could not load notices'),
      );
  }, []);

  return (
    <div className="min-h-screen bg-muted">
      <header className="border-b border-border bg-card">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-5">
          <div className="flex items-center gap-2">
            <Car className="h-9 w-9 text-brand-lime" />
            <div>
              <div className="text-lg font-bold tracking-tight">
                <span className="text-brand-lime">CAR</span>
                <span className="text-foreground">FLUENCER</span>
              </div>
              <p className="text-xs text-muted-foreground">× Evo.ad — MVP portals</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <ThemeToggle variant="compact" />
            <p className="hidden text-xs text-muted-foreground sm:block">
              API: <code className="rounded bg-muted px-1.5 py-0.5 font-mono">VITE_API_URL</code>
            </p>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-6 py-10">
        <h1 className="mb-3 text-3xl font-bold tracking-tight text-foreground">Welcome</h1>
        <p className="mb-10 max-w-2xl text-sm text-muted-foreground">
          Choose a portal. Content blocks below are managed in Filament and loaded from{' '}
          <code className="rounded bg-card px-1 font-mono text-xs ring-1 ring-border">GET /api/content-blocks</code>.
        </p>

        {blocksError ? <p className="mb-6 text-sm text-red-600">{blocksError}</p> : null}
        {blocks === null && !blocksError ? <p className="mb-6 text-sm text-muted-foreground">Loading notices…</p> : null}
        {blocks?.length === 0 ? <p className="mb-6 text-sm text-muted-foreground">No public notices.</p> : null}
        <div className="mb-10 space-y-4">
          {blocks?.map((b) => (
            <article key={b.id} className="cf-card">
              <h2 className="mb-2 text-lg font-semibold text-foreground">{b.title}</h2>
              <ContentBlockBody body={b.body} />
              <p className="mt-3 text-xs text-muted-foreground">
                key: <code className="font-mono">{b.key}</code>
              </p>
            </article>
          ))}
        </div>

        <div className="grid gap-6 sm:grid-cols-2">
          <div className="cf-card border-t-4 border-t-brand-lime">
            <h2 className="mb-2 text-lg font-semibold text-foreground">Media owner</h2>
            <p className="mb-6 text-sm text-muted-foreground">Fleet, campaigns you participate in, earnings.</p>
            <div className="flex flex-wrap gap-3">
              <Link className="cf-btn" to="/media-owner/login">
                Login
              </Link>
              <Link className="cf-btn-secondary" to="/media-owner/register">
                Register
              </Link>
            </div>
          </div>
          <div className="cf-card border-t-4 border-t-brand-magenta">
            <h2 className="mb-2 text-lg font-semibold text-foreground">Advertiser</h2>
            <p className="mb-6 text-sm text-muted-foreground">Campaigns, catalog, heatmap, pricing.</p>
            <div className="flex flex-wrap gap-3">
              <Link className="cf-btn" to="/advertiser/login">
                Login
              </Link>
              <Link className="cf-btn-secondary" to="/advertiser/register">
                Register
              </Link>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
