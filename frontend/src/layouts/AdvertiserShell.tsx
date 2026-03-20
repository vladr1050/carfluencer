import { LayoutDashboard, Car, Briefcase, Map, Banknote, Percent, LogOut } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { ThemeToggle } from '../components/ThemeToggle';

function navCls({ isActive }: { isActive: boolean }): string {
  return `flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors ${
    isActive
      ? 'bg-brand-lime text-black shadow-sm'
      : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
  }`;
}

export function AdvertiserShell({ children }: { children: React.ReactNode }): JSX.Element {
  const { logout } = useAuth();

  return (
    <div className="flex min-h-screen bg-muted">
      <aside className="flex w-64 shrink-0 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
        <div className="border-b border-sidebar-border p-6">
          <div className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-magenta text-xs font-bold text-white">
              AD
            </div>
            <div>
              <div className="text-lg font-bold tracking-tight">
                <span className="text-brand-lime">CAR</span>
                <span className="text-foreground">FLUENCER</span>
              </div>
              <p className="text-xs text-muted-foreground">Advertiser</p>
            </div>
          </div>
        </div>
        <nav className="flex flex-1 flex-col gap-1 p-3">
          <NavLink to="/advertiser" end className={navCls}>
            <LayoutDashboard className="h-5 w-5 shrink-0" />
            Dashboard
          </NavLink>
          <NavLink to="/advertiser/campaigns" className={navCls}>
            <Briefcase className="h-5 w-5 shrink-0" />
            Campaigns
          </NavLink>
          <NavLink to="/advertiser/vehicles" className={navCls}>
            <Car className="h-5 w-5 shrink-0" />
            Vehicles
          </NavLink>
          <NavLink to="/advertiser/heatmap" className={navCls}>
            <Map className="h-5 w-5 shrink-0" />
            Heatmap
          </NavLink>
          <NavLink to="/advertiser/pricing" className={navCls}>
            <Banknote className="h-5 w-5 shrink-0" />
            Pricing
          </NavLink>
          <NavLink to="/advertiser/discounts" className={navCls}>
            <Percent className="h-5 w-5 shrink-0" />
            Discounts
          </NavLink>
        </nav>
        <div className="space-y-2 border-t border-sidebar-border p-3">
          <ThemeToggle />
          <button type="button" className="cf-btn-secondary w-full" onClick={() => void logout()}>
            <LogOut className="h-5 w-5 shrink-0" />
            Log out
          </button>
        </div>
      </aside>
      <main className="mx-auto w-full max-w-5xl flex-1 overflow-auto p-6 lg:p-10">{children}</main>
    </div>
  );
}
