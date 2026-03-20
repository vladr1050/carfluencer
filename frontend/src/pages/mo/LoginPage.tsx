import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Car } from 'lucide-react';
import { useAuth } from '../../auth/AuthContext';

export default function MoLoginPage(): JSX.Element {
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
      if (user.role !== 'media_owner') {
        await logout();
        setError('This portal is for media owners only.');
        return;
      }
      navigate('/media-owner', { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen">
      <div className="hidden flex-1 flex-col justify-between bg-black p-10 text-white lg:flex">
        <div className="flex items-center gap-2">
          <Car className="h-10 w-10 text-brand-lime" />
          <span className="text-xl font-bold tracking-tight">
            <span className="text-brand-lime">CAR</span>FLUENCER
          </span>
        </div>
        <div>
          <p className="text-3xl font-bold leading-tight text-brand-lime">Media owner portal</p>
          <p className="mt-4 max-w-md text-sm text-white/75">Register vehicles, track campaigns, and view earnings.</p>
        </div>
        <p className="text-xs text-white/45">Evo.ad × Carfluencer MVP</p>
      </div>
      <div className="flex flex-1 items-center justify-center bg-muted p-6">
        <div className="w-full max-w-md rounded-2xl border border-border bg-card p-8 shadow-sm">
          <h1 className="mb-6 text-xl font-bold text-foreground">Sign in</h1>
          <form className="space-y-4" onSubmit={(e) => void onSubmit(e)}>
            <div>
              <label className="cf-label" htmlFor="em">
                Email
              </label>
              <input
                id="em"
                className="cf-input"
                type="email"
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="cf-label" htmlFor="pw">
                Password
              </label>
              <input
                id="pw"
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
            <Link className="font-medium text-brand-magenta hover:underline" to="/media-owner/register">
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
