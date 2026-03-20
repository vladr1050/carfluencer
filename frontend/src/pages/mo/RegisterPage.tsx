import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';

export default function MoRegisterPage(): JSX.Element {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [companyName, setCompanyName] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent): Promise<void> {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const user = await register({
        name,
        email,
        company_name: companyName,
        phone: phone || undefined,
        password,
        password_confirmation: passwordConfirmation,
        role: 'media_owner',
      });
      if (user.role !== 'media_owner') {
        setError('Unexpected role after registration.');
        return;
      }
      navigate('/media-owner', { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted p-6">
      <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-8 shadow-sm">
        <h1 className="mb-2 text-xl font-bold text-foreground">Media owner registration</h1>
        <p className="mb-6 text-sm text-muted-foreground">Company name is required by the API.</p>
        <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => void onSubmit(e)}>
          <div className="sm:col-span-1">
            <label className="cf-label">Name</label>
            <input className="cf-input" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="sm:col-span-1">
            <label className="cf-label">Email</label>
            <input className="cf-input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <div className="sm:col-span-2">
            <label className="cf-label">Company name</label>
            <input className="cf-input" value={companyName} onChange={(e) => setCompanyName(e.target.value)} required />
          </div>
          <div className="sm:col-span-2">
            <label className="cf-label">Phone (optional)</label>
            <input className="cf-input" value={phone} onChange={(e) => setPhone(e.target.value)} />
          </div>
          <div className="sm:col-span-1">
            <label className="cf-label">Password</label>
            <input className="cf-input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} />
          </div>
          <div className="sm:col-span-1">
            <label className="cf-label">Confirm</label>
            <input
              className="cf-input"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
            />
          </div>
          <div className="sm:col-span-2">
            {error ? <p className="mb-2 text-sm text-red-600">{error}</p> : null}
            <button className="cf-btn" type="submit" disabled={loading}>
              {loading ? 'Creating…' : 'Create account'}
            </button>
          </div>
        </form>
        <p className="mt-6 text-center text-sm text-muted-foreground">
          <Link className="font-medium text-brand-magenta hover:underline" to="/media-owner/login">
            Already have an account
          </Link>
        </p>
      </div>
    </div>
  );
}
