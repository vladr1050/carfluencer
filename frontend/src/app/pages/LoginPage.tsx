import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { Car, Moon, Sun } from 'lucide-react';
import { useTheme } from 'next-themes';
import { useAuth } from '@/auth/AuthContext';
import { formatUnknownError, getApiBase } from '@/lib/api';

export function LoginPage() {
  const [userType, setUserType] = useState<'mediaowner' | 'advertiser'>('advertiser');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();
  const { theme, setTheme } = useTheme();
  const { login, logout, user, loading } = useAuth();

  if (!loading && user) {
    if (user.role === 'advertiser') {
      return <Navigate to="/advertiser" replace />;
    }
    if (user.role === 'media_owner') {
      return <Navigate to="/media-owner" replace />;
    }
  }

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const user = await login(email, password);
      const wantMo = userType === 'mediaowner';
      if (wantMo && user.role !== 'media_owner') {
        await logout();
        setError('This account is not a media owner. Switch tab or use the correct email.');
        return;
      }
      if (!wantMo && user.role !== 'advertiser') {
        await logout();
        setError('This account is not an advertiser. Switch tab or use the correct email.');
        return;
      }
      navigate(wantMo ? '/media-owner' : '/advertiser', { replace: true });
    } catch (err) {
      setError(formatUnknownError(err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <button
        type="button"
        onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
        className="fixed top-4 right-4 p-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors"
      >
        {theme === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
      </button>

      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <div className="inline-flex items-center gap-3 mb-4">
            <Car className="w-12 h-12" style={{ color: '#C1F60D' }} />
            <h1 className="text-4xl tracking-tight" style={{ fontFamily: 'Inter, Helvetica Neue, sans-serif' }}>
              <span style={{ color: '#C1F60D' }}>CAR</span>
              <span className="text-foreground">FLUENCER</span>
            </h1>
          </div>
          <p className="text-muted-foreground">Mobility Data Platform</p>
          <p className="mt-2 text-xs text-muted-foreground">
            API:{' '}
            <code className="rounded bg-muted px-1">
              {getApiBase() || (import.meta.env.DEV ? 'same tab → Vite proxy → Laravel :8000' : 'set VITE_API_URL')}
            </code>
          </p>
        </div>

        <div className="bg-card border border-border rounded-lg p-6 shadow-lg">
          <div className="flex gap-2 mb-6">
            <button
              type="button"
              onClick={() => setUserType('advertiser')}
              className={`flex-1 py-2 px-4 rounded-md transition-all ${
                userType === 'advertiser'
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground hover:bg-accent'
              }`}
            >
              Advertiser
            </button>
            <button
              type="button"
              onClick={() => setUserType('mediaowner')}
              className={`flex-1 py-2 px-4 rounded-md transition-all ${
                userType === 'mediaowner'
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground hover:bg-accent'
              }`}
            >
              Media Owner
            </button>
          </div>

          <form onSubmit={(e) => void handleLogin(e)} className="space-y-4">
            <div>
              <label className="block text-sm mb-2">Email</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-4 py-2 rounded-md bg-input-background dark:bg-input border border-border focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                placeholder="your@email.com"
                required
              />
            </div>

            <div>
              <label className="block text-sm mb-2">Password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-4 py-2 rounded-md bg-input-background dark:bg-input border border-border focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                placeholder="••••••••"
                required
              />
            </div>

            {error ? <p className="text-sm text-destructive">{error}</p> : null}

            <button
              type="submit"
              disabled={submitting}
              className="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:opacity-90 transition-opacity disabled:opacity-50"
            >
              {submitting ? 'Signing in…' : 'Sign In'}
            </button>
          </form>

          <p className="mt-4 text-center text-sm text-muted-foreground">
            No account?{' '}
            <Link to="/register" className="text-primary font-medium hover:underline">
              Register
            </Link>
            {' · '}
            <Link to="/welcome" className="text-muted-foreground hover:text-foreground hover:underline">
              Public notices
            </Link>
          </p>

          <div className="mt-4 rounded-md bg-muted/50 p-3 text-left text-xs text-muted-foreground space-y-1">
            <p className="font-medium text-foreground">After `php artisan migrate --seed`:</p>
            <p>
              <strong>Advertiser:</strong> advertiser@brand.test / password
            </p>
            <p>
              <strong>Media owner:</strong> media@carguru.test / password
            </p>
            <p className="pt-1">
              Filament admin <code className="text-[11px]">admin@carfluencer.test</code> cannot use these portal tabs (role admin).
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
