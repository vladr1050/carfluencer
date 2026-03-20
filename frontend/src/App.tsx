import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth/AuthContext';
import AdvCampaignDetailPage from './pages/adv/CampaignDetailPage';
import AdvCampaignsPage from './pages/adv/CampaignsPage';
import AdvDashboardPage from './pages/adv/DashboardPage';
import AdvDiscountsPage from './pages/adv/DiscountsPage';
import AdvHeatmapPage from './pages/adv/HeatmapPage';
import AdvLoginPage from './pages/adv/LoginPage';
import AdvPricingPage from './pages/adv/PricingPage';
import AdvRegisterPage from './pages/adv/RegisterPage';
import AdvVehicleDetailPage from './pages/adv/VehicleDetailPage';
import AdvVehiclesPage from './pages/adv/VehiclesPage';
import HomePage from './pages/HomePage';
import MoCampaignDetailPage from './pages/mo/CampaignDetailPage';
import MoCampaignsPage from './pages/mo/CampaignsPage';
import MoDashboardPage from './pages/mo/DashboardPage';
import MoEarningsPage from './pages/mo/EarningsPage';
import MoLoginPage from './pages/mo/LoginPage';
import MoRegisterPage from './pages/mo/RegisterPage';
import MoVehiclesPage from './pages/mo/VehiclesPage';

function RequireRole({
  role,
  children,
}: {
  role: 'media_owner' | 'advertiser';
  children: JSX.Element;
}): JSX.Element {
  const { user, loading } = useAuth();
  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background text-sm text-muted-foreground">
        Loading…
      </div>
    );
  }
  if (!user) {
    return <Navigate to={role === 'media_owner' ? '/media-owner/login' : '/advertiser/login'} replace />;
  }
  if (user.role !== role) {
    return <Navigate to="/" replace />;
  }
  return children;
}

export default function App(): JSX.Element {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />

      <Route path="/media-owner/login" element={<MoLoginPage />} />
      <Route path="/media-owner/register" element={<MoRegisterPage />} />
      <Route
        path="/media-owner"
        element={
          <RequireRole role="media_owner">
            <MoDashboardPage />
          </RequireRole>
        }
      />
      <Route
        path="/media-owner/vehicles"
        element={
          <RequireRole role="media_owner">
            <MoVehiclesPage />
          </RequireRole>
        }
      />
      <Route
        path="/media-owner/campaigns"
        element={
          <RequireRole role="media_owner">
            <MoCampaignsPage />
          </RequireRole>
        }
      />
      <Route
        path="/media-owner/campaigns/:campaignId"
        element={
          <RequireRole role="media_owner">
            <MoCampaignDetailPage />
          </RequireRole>
        }
      />
      <Route
        path="/media-owner/earnings"
        element={
          <RequireRole role="media_owner">
            <MoEarningsPage />
          </RequireRole>
        }
      />

      <Route path="/advertiser/login" element={<AdvLoginPage />} />
      <Route path="/advertiser/register" element={<AdvRegisterPage />} />
      <Route
        path="/advertiser"
        element={
          <RequireRole role="advertiser">
            <AdvDashboardPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/vehicles"
        element={
          <RequireRole role="advertiser">
            <AdvVehiclesPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/vehicles/:vehicleId"
        element={
          <RequireRole role="advertiser">
            <AdvVehicleDetailPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/campaigns"
        element={
          <RequireRole role="advertiser">
            <AdvCampaignsPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/campaigns/:campaignId"
        element={
          <RequireRole role="advertiser">
            <AdvCampaignDetailPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/heatmap"
        element={
          <RequireRole role="advertiser">
            <AdvHeatmapPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/pricing"
        element={
          <RequireRole role="advertiser">
            <AdvPricingPage />
          </RequireRole>
        }
      />
      <Route
        path="/advertiser/discounts"
        element={
          <RequireRole role="advertiser">
            <AdvDiscountsPage />
          </RequireRole>
        }
      />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
