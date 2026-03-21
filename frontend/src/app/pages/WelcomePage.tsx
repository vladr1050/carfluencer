import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Car, Moon, Sun } from 'lucide-react';
import { useTheme } from 'next-themes';
import { apiJson, formatUnknownError } from '@/lib/api';
import { ContentBlockBody } from '../components/ContentBlockBody';

type ContentBlock = {
  id: number;
  key: string;
  title: string;
  body: string;
};

export function WelcomePage(): JSX.Element {
  const [blocks, setBlocks] = useState<ContentBlock[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const { theme, setTheme } = useTheme();

  useEffect(() => {
    apiJson<{ data: ContentBlock[] }>('/api/content-blocks')
      .then((res) => setBlocks(res.data))
      .catch((e: unknown) => setError(formatUnknownError(e)));
  }, []);

  return (
    <div className="min-h-screen bg-background">
      <button
        type="button"
        onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
        className="fixed top-4 right-4 z-10 rounded-lg border border-border bg-card p-2 hover:bg-accent"
        aria-label="Toggle theme"
      >
        {theme === 'dark' ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
      </button>

      <header className="border-b border-border bg-card">
        <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4 px-6 py-6">
          <div className="flex items-center gap-3">
            <Car className="h-10 w-10" style={{ color: '#C1F60D' }} />
            <div>
              <h1 className="text-xl font-semibold tracking-tight" style={{ fontFamily: 'Inter, Helvetica Neue, sans-serif' }}>
                <span style={{ color: '#C1F60D' }}>CAR</span>
                <span className="text-foreground">FLUENCER</span>
              </h1>
              <p className="text-xs text-muted-foreground">× Evo.ad — public notices</p>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link
              to="/login"
              className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
              Login
            </Link>
            <Link
              to="/register"
              className="rounded-md border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-accent"
            >
              Register
            </Link>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-6 py-10">
        <p className="mb-8 text-sm text-muted-foreground">
          Notices from Filament via <code className="rounded bg-muted px-1 font-mono text-xs">GET /api/content-blocks</code>.
        </p>

        {error ? <p className="mb-6 text-sm text-destructive">{error}</p> : null}
        {blocks === null && !error ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
        {blocks?.length === 0 ? <p className="text-sm text-muted-foreground">No public notices.</p> : null}

        <div className="space-y-6">
          {blocks?.map((b) => (
            <article key={b.id} className="rounded-lg border border-border bg-card p-6 shadow-sm">
              <h2 className="mb-2 text-lg font-semibold text-foreground">{b.title}</h2>
              <ContentBlockBody body={b.body} />
              <p className="mt-3 text-xs text-muted-foreground">
                key: <code className="font-mono">{b.key}</code>
              </p>
            </article>
          ))}
        </div>
      </main>
    </div>
  );
}
