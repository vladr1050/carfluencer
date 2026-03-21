import { createBrowserRouter } from 'react-router-dom';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { WelcomePage } from './pages/WelcomePage';
import { MediaOwnerLayout } from './layouts/MediaOwnerLayout';
import { AdvertiserLayout } from './layouts/AdvertiserLayout';
import { MediaOwnerDashboard } from './pages/mediaowner/Dashboard';
import { MediaOwnerVehicles } from './pages/mediaowner/Vehicles';
import { MediaOwnerCampaigns } from './pages/mediaowner/Campaigns';
import { MediaOwnerEarnings } from './pages/mediaowner/Earnings';
import { MediaOwnerVehicleDetails } from './pages/mediaowner/VehicleDetails';
import { MediaOwnerCampaignDetails } from './pages/mediaowner/CampaignDetails';
import { AdvertiserDashboard } from './pages/advertiser/Dashboard';
import { AdvertiserVehicles } from './pages/advertiser/Vehicles';
import { AdvertiserCampaigns } from './pages/advertiser/Campaigns';
import { AdvertiserHeatmap } from './pages/advertiser/Heatmap';
import { AdvertiserVehicleDetails } from './pages/advertiser/VehicleDetails';
import { AdvertiserCampaignDetails } from './pages/advertiser/CampaignDetails';
import { AdvertiserPricing } from './pages/advertiser/Pricing';
import { AdvertiserDiscounts } from './pages/advertiser/Discounts';

export const router = createBrowserRouter([
  {
    path: '/',
    Component: LoginPage,
  },
  {
    path: '/login',
    Component: LoginPage,
  },
  {
    path: '/register',
    Component: RegisterPage,
  },
  {
    path: '/welcome',
    Component: WelcomePage,
  },
  {
    path: '/media-owner',
    Component: MediaOwnerLayout,
    children: [
      { index: true, Component: MediaOwnerDashboard },
      { path: 'vehicles', Component: MediaOwnerVehicles },
      { path: 'vehicles/:id', Component: MediaOwnerVehicleDetails },
      { path: 'campaigns', Component: MediaOwnerCampaigns },
      { path: 'campaigns/:id', Component: MediaOwnerCampaignDetails },
      { path: 'earnings', Component: MediaOwnerEarnings },
    ],
  },
  {
    path: '/advertiser',
    Component: AdvertiserLayout,
    children: [
      { index: true, Component: AdvertiserDashboard },
      { path: 'vehicles', Component: AdvertiserVehicles },
      { path: 'vehicles/:id', Component: AdvertiserVehicleDetails },
      { path: 'campaigns', Component: AdvertiserCampaigns },
      { path: 'campaigns/:id', Component: AdvertiserCampaignDetails },
      { path: 'heatmap', Component: AdvertiserHeatmap },
      { path: 'pricing', Component: AdvertiserPricing },
      { path: 'discounts', Component: AdvertiserDiscounts },
    ],
  },
]);
