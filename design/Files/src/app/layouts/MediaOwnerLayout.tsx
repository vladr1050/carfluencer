import { Outlet, useNavigate, useLocation, Navigate } from 'react-router';
import { LayoutDashboard, Car, Briefcase, DollarSign, LogOut, Moon, Sun } from 'lucide-react';
import { useTheme } from 'next-themes';
import { useAuth } from '@/auth/AuthContext';

export function MediaOwnerLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { theme, setTheme } = useTheme();
  const { user, loading, logout } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background text-muted-foreground">
        Loading…
      </div>
    );
  }
  if (!user || user.role !== 'media_owner') {
    return <Navigate to="/login" replace />;
  }

  const menuItems = [
    { path: '/mediaowner', label: 'Dashboard', icon: LayoutDashboard },
    { path: '/mediaowner/vehicles', label: 'Vehicles', icon: Car },
    { path: '/mediaowner/campaigns', label: 'Campaigns', icon: Briefcase },
    { path: '/mediaowner/earnings', label: 'Earnings', icon: DollarSign },
  ];

  const isActive = (path: string) => {
    if (path === '/mediaowner') {
      return location.pathname === path;
    }
    return location.pathname.startsWith(path);
  };

  return (
    <div className="min-h-screen bg-background flex">
      {/* Sidebar */}
      <div className="w-64 bg-sidebar border-r border-sidebar-border flex flex-col">
        <div className="p-6 border-b border-sidebar-border">
          <div className="flex items-center gap-2">
            <Car className="w-8 h-8" style={{ color: '#C1F60D' }} />
            <div>
              <h1 className="tracking-tight" style={{ fontFamily: 'Inter, Helvetica Neue, sans-serif' }}>
                <span style={{ color: '#C1F60D' }}>CAR</span>
                <span className="text-sidebar-foreground">FLUENCER</span>
              </h1>
              <p className="text-xs text-muted-foreground">Media Owner</p>
            </div>
          </div>
        </div>

        <nav className="flex-1 p-4 space-y-2">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const active = isActive(item.path);
            return (
              <button
                key={item.path}
                type="button"
                onClick={() => navigate(item.path)}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                  active
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent'
                }`}
              >
                <Icon className="w-5 h-5" />
                <span>{item.label}</span>
              </button>
            );
          })}
        </nav>

        <div className="p-4 border-t border-sidebar-border space-y-2">
          <button
            type="button"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sidebar-foreground hover:bg-sidebar-accent transition-colors"
          >
            {theme === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
            <span>Theme</span>
          </button>
          <button
            type="button"
            onClick={() => {
              void logout().then(() => navigate('/login', { replace: true }));
            }}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sidebar-foreground hover:bg-sidebar-accent transition-colors"
          >
            <LogOut className="w-5 h-5" />
            <span>Logout</span>
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="flex-1 overflow-auto">
        <Outlet />
      </div>
    </div>
  );
}