import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';
import { Car, Moon, Sun } from 'lucide-react';
import { useTheme } from 'next-themes';
import { apiJson, formatUnknownError, setToken } from '@/lib/api';
import { useAuth } from '@/auth/AuthContext';

type RoleTab = 'advertiser' | 'mediaowner';

export function RegisterPage() {
  const [roleTab, setRoleTab] = useState<RoleTab>('advertiser');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [companyName, setCompanyName] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();
  const { theme, setTheme } = useTheme();
  const { refreshMe, user, loading } = useAuth();

  if (!loading && user && (user.role === 'advertiser' || user.role === 'media_owner')) {
    return <Navigate to={user.role === 'media_owner' ? '/mediaowner' : '/advertiser'} replace />;
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const res = await apiJson<{ token: string; user: { role: string } }>('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify({
          name,
          email,
          password,
          password_confirmation: passwordConfirmation,
          role: roleTab === 'mediaowner' ? 'media_owner' : 'advertiser',
          company_name: companyName,
        }),
      });
      setToken(res.token);
      await refreshMe();
      navigate(res.user.role === 'media_owner' ? '/mediaowner' : '/advertiser', { replace: true });
    } catch (err) {
      setError(formatUnknownError(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <button
        type="button"
        onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
        className="fixed top-4 right-4 p-2 rounded-lg border border-border bg-card"
      >
        {theme === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
      </button>

      <div className="w-full max-w-md">
        <div className="text-center mb-6">
          <div className="inline-flex items-center gap-3 mb-3">
            <Car className="w-10 h-10" style={{ color: '#C1F60D' }} />
            <h1 className="text-3xl tracking-tight">
              <span style={{ color: '#C1F60D' }}>CAR</span>
              <span className="text-foreground">FLUENCER</span>
            </h1>
          </div>
          <p className="text-muted-foreground text-sm">Create account</p>
        </div>

        <div className="bg-card border border-border rounded-lg p-6 shadow-lg">
          <div className="flex gap-2 mb-6">
            <button
              type="button"
              onClick={() => setRoleTab('advertiser')}
              className={`flex-1 py-2 px-4 rounded-md text-sm font-medium ${
                roleTab === 'advertiser' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
              }`}
            >
              Advertiser
            </button>
            <button
              type="button"
              onClick={() => setRoleTab('mediaowner')}
              className={`flex-1 py-2 px-4 rounded-md text-sm font-medium ${
                roleTab === 'mediaowner' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
              }`}
            >
              Media owner
            </button>
          </div>

          <form onSubmit={(e) => void onSubmit(e)} className="space-y-4">
            <div>
              <label className="block text-sm mb-1">Name</label>
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                className="w-full px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
              />
            </div>
            <div>
              <label className="block text-sm mb-1">Email</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
              />
            </div>
            <div>
              <label className="block text-sm mb-1">Company name</label>
              <input
                value={companyName}
                onChange={(e) => setCompanyName(e.target.value)}
                required
                className="w-full px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
              />
            </div>
            <div>
              <label className="block text-sm mb-1">Password (min 8)</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={8}
                className="w-full px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
              />
            </div>
            <div>
              <label className="block text-sm mb-1">Confirm password</label>
              <input
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                required
                className="w-full px-3 py-2 rounded-md border border-border bg-input-background dark:bg-input text-sm"
              />
            </div>
            {error ? <p className="text-sm text-destructive">{error}</p> : null}
            <button
              type="submit"
              disabled={submitting}
              className="w-full py-2.5 rounded-md bg-primary text-primary-foreground font-medium disabled:opacity-50"
            >
              {submitting ? 'Creating…' : 'Register'}
            </button>
          </form>

          <p className="mt-4 text-center text-sm text-muted-foreground">
            <Link to="/login" className="text-primary hover:underline">
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
