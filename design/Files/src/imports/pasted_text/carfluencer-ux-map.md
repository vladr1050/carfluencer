Ниже — Screen Map (UX Architecture) для порталов /mediaowner и /advertiser.
Это именно тот формат, который дизайнер может сразу использовать в Figma (как структуру фреймов).
Я сделал его MVP-ориентированным, чтобы не перегружать продукт на старте.

CARFLUENCER UX Screen Map (MVP)
CARFLUENCER PLATFORM
│
├── AUTH
│   ├── Login
│   ├── Registration
│   └── Forgot Password
│
├── MEDIA OWNER PORTAL
│   │
│   ├── Dashboard
│   │   ├── Vehicles Overview
│   │   ├── Active Campaigns
│   │   ├── Earnings Summary
│   │   └── Monthly Earnings Chart
│   │
│   ├── Vehicles
│   │   ├── Vehicles List
│   │   │   ├── Vehicle Card / Row
│   │   │   └── Add Vehicle
│   │   │
│   │   └── Vehicle Details
│   │       ├── Vehicle Info
│   │       ├── Vehicle Image
│   │       ├── Campaign Participation
│   │       └── Vehicle Earnings
│   │
│   ├── Campaigns
│   │   ├── Campaign List
│   │   │
│   │   └── Campaign Details
│   │       ├── Campaign Info
│   │       ├── Vehicles Involved
│   │       └── Upload Photo Proof
│   │
│   └── Earnings
│       ├── Earnings Summary
│       ├── Earnings by Vehicle
│       └── Monthly Earnings Chart
│
│
├── ADVERTISER PORTAL
│   │
│   ├── Dashboard
│   │   ├── Active Campaigns
│   │   ├── Vehicles Used
│   │   ├── Total Impressions
│   │   └── Exposure Chart
│   │
│   ├── Vehicles
│   │   ├── Vehicles Inventory
│   │   │   ├── Vehicle Cards
│   │   │   └── Filters
│   │   │       ├── Brand
│   │   │       ├── Model
│   │   │       └── Price
│   │   │
│   │   └── Vehicle Details
│   │       ├── Vehicle Info
│   │       ├── Campaign Participation
│   │       └── Exposure Data
│   │
│   ├── Campaigns
│   │   ├── Campaign List
│   │   │
│   │   └── Campaign Details
│   │       ├── Campaign Info
│   │       ├── Vehicles
│   │       ├── Campaign Dates
│   │       └── Performance Summary
│   │
│   └── HEATMAP (CORE SCREEN)
│       ├── Map View
│       │   ├── Heatmap Layer
│       │   ├── Vehicle Tracks
│       │   └── Parking Points
│       │
│       ├── Filters
│       │   ├── Vehicle
│       │   ├── Date Range
│       │   ├── Driving
│       │   ├── Parking
│       │   └── Both
│       │
│       └── Analytics Panel
│           ├── Impressions
│           ├── Driving Distance
│           ├── Parking Time
│           └── Top Zones
│
│
└── GLOBAL COMPONENTS
    ├── Sidebar Navigation
    ├── Top Navigation
    ├── Profile Menu
    ├── Theme Switch (Light / Dark)
    └── Notifications
Навигация (как это должно выглядеть в UI)
Sidebar — MediaOwner
Dashboard
Vehicles
Campaigns
Earnings
Sidebar — Advertiser
Dashboard
Vehicles
Campaigns
Heatmap
Самый главный экран продукта
Это Heatmap screen.
UX должен выглядеть примерно так:

------------------------------------------------
| Vehicle | Date | Driving | Parking | Apply   |
------------------------------------------------

                CITY MAP
                HEATMAP

------------------------------------------------
| Impressions | Driving km | Parking time      |
------------------------------------------------
Это главный selling point платформы.
Figma структура (как дизайнер должен организовать файл)
FIGMA FILE

1️⃣ Design System
   - Colors
   - Typography
   - Buttons
   - Inputs
   - Cards
   - Tables
   - Map UI
   - Charts

2️⃣ Auth

3️⃣ MediaOwner Portal

4️⃣ Advertiser Portal

5️⃣ Components
Критически важный UX момент
Heatmap должен выглядеть очень круто.
Он должен давать ощущение:

Mobility Data Platform
+
Advertising Analytics
Это превращает CARFLUENCER из:
❌ просто "рекламного маркетплейса"

в

✅ Mobility Data Platform
