import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';

export default function AdvLoginPage(): JSX.Element {
  const { login, logout } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const user = await login(email, password);
      if (user.role !== 'advertiser') {
        await logout();
        setError('This portal is for advertisers only.');
        return;
      }
      navigate('/advertiser', { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen">
      <div className="hidden flex-1 flex-col justify-between bg-gradient-to-br from-brand-black via-brand-gray to-brand-magenta p-10 text-white lg:flex">
        <div className="text-xl font-bold tracking-tight">
          <span className="text-brand-lime">CAR</span>FLUENCER
        </div>
        <div>
          <p className="text-3xl font-bold">Advertiser portal</p>
          <p className="mt-4 max-w-md text-sm text-white/80">Campaigns, pricing, heatmaps, and fleet visibility.</p>
        </div>
        <p className="text-xs text-white/40">Evo.ad × Carfluencer MVP</p>
      </div>
      <div className="flex flex-1 items-center justify-center bg-muted p-6">
        <div className="w-full max-w-md rounded-2xl border border-border bg-card p-8 shadow-sm">
          <h1 className="mb-6 text-xl font-bold text-foreground">Sign in</h1>
          <form className="space-y-4" onSubmit={(e) => void onSubmit(e)}>
            <div>
              <label className="cf-label" htmlFor="aem">
                Email
              </label>
              <input
                id="aem"
                className="cf-input"
                type="email"
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="cf-label" htmlFor="apw">
                Password
              </label>
              <input
                id="apw"
                className="cf-input"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            {error ? <p className="text-sm text-red-600">{error}</p> : null}
            <button className="cf-btn w-full" type="submit" disabled={loading}>
              {loading ? 'Signing in…' : 'Sign in'}
            </button>
          </form>
          <p className="mt-6 text-center text-sm text-muted-foreground">
            <Link className="font-medium text-brand-magenta hover:underline" to="/advertiser/register">
              Create account
            </Link>
            {' · '}
            <Link to="/" className="hover:underline">
              Home
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
