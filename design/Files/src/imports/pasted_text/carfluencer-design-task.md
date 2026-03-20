Figma UX/UI Design Task
CARFLUENCER — MediaOwner & Advertiser Portals
Project Overview
Design the UX/UI for the CARFLUENCER platform portals.
The platform allows companies to:
• place advertisements on vehicles
• track advertising exposure
• visualize vehicle activity on maps
The core product value is data visualization from vehicle telemetry.
Advertisers should clearly see where advertising vehicles drove and parked in the city.
The first MVP focuses on:
• vehicle inventory
• advertising campaigns
• telemetry heatmap visualization
• earnings tracking
Design Scope
Design two web portals:
1️⃣ MediaOwner Portal
2️⃣ Advertiser Portal
Admin panel is NOT part of this design task (it uses Filament).
Brand Style Guide
Design must follow the CARFLUENCER Visual Style Guide. 
Visual_style_guide_CARFLUENCER
Key brand elements:
Primary Colors
Neon Green
#C1F60D
Magenta Accent
#F10DBF
Black
#000000
Gray
#545454
White
#FFFFFF
These colors must be used consistently in the UI system. 
Visual_style_guide_CARFLUENCER
Typography
Primary font:
HELIOS
If Helios is not available on web:
Fallback:
Inter
or
Helvetica Neue
Typography should emphasize:
• large headlines
• clean modern UI
• tech / mobility aesthetics. 
Visual_style_guide_CARFLUENCER
Visual Style Direction
The UI should feel:
• modern
• technological
• mobility-focused
• data-driven
Visual inspiration:
• Uber dashboards
• Tesla UI
• mobility SaaS platforms
• analytics dashboards
The design should highlight:
• maps
• vehicle activity
• heatmaps
• statistics
Themes
The platform must support:
Light Theme
white background
dark typography
Dark Theme
black background
neon accents
Dark theme should feel premium and tech-oriented.
Core UX Principles
1️⃣ Map-first design
Maps and heatmaps are the core UI component.
2️⃣ Data transparency
Advertisers must easily understand exposure.
3️⃣ Simple navigation
Few clear sections.
4️⃣ Dashboard-style interface
Focus on data visualization.
Portal 1 — MediaOwner
Media owners are vehicle providers.
They want to:
• register vehicles
• see campaigns
• see earnings.
MediaOwner Screens
Login / Registration
email
password
company name
Dashboard
Overview cards:
Vehicles
Active Campaigns
Total Earnings
Charts:
Monthly earnings
Vehicles in campaigns
Vehicles
Table view:
Brand
Model
Year
Color
Quantity
Image
Status
Actions:
Add vehicle
Edit vehicle
Vehicle Details
Vehicle image
vehicle specs
Campaigns where the vehicle participates
Revenue generated
Campaign Participation
List of campaigns.
For each campaign:
Campaign name
Dates
Vehicle used
Upload photo proof of advertisement installed on vehicle.
Portal 2 — Advertiser
Advertisers are companies buying advertising campaigns.
They want to:
• see available vehicles
• see campaigns
• analyze heatmap data.
Advertiser Screens
Login / Registration
company name
email
password
Dashboard
Cards:
Active Campaigns
Vehicles Used
Total Reach / Impressions
Charts:
Exposure over time
Vehicles Inventory
Grid or table view.
Vehicle cards include:
image
brand
model
year
price
Filter:
brand
type
availability
Campaigns
List of campaigns.
Campaign details include:
Vehicles used
Campaign duration
Campaign status
Heatmap (Core Screen)
This is the most important screen.
Features:
Interactive map.
Heatmap overlay showing:
• driving activity
• parking activity
Filters:
Vehicle
Date range
Driving
Parking
Both
Map layers:
vehicle tracks
parking points
zones (optional)
Map Design
Map must support:
• heatmap layer
• vehicle tracks
• city overview
Design should highlight:
where ads were seen most often.
UI Components
Design reusable components:
Buttons
Primary
Secondary
Tables
Filters
Date pickers
Vehicle cards
Charts
Map widgets
Navigation Structure
Left sidebar navigation.
MediaOwner:
Dashboard
Vehicles
Campaigns
Earnings
Advertiser:
Dashboard
Vehicles
Campaigns
Heatmap
Responsive Design
Design for:
Desktop
Tablet
Mobile support optional for MVP.
Design Deliverables
Figma file must include:
Pages
Design System
MediaOwner Portal
Advertiser Portal
Design System
Color tokens
Typography
Buttons
Inputs
Cards
Tables
Charts
Screens
Login
Dashboard
Vehicles
Vehicle Details
Campaigns
Heatmap
UX Priority
1️⃣ Heatmap screen
2️⃣ Dashboard analytics
3️⃣ Vehicles inventory
4️⃣ Campaign management
Technical Stack
The UI will be implemented using:
React
Tailwind
PostgreSQL backend
Maps:
Leaflet or Mapbox.
Goal of Design
Create a clean, modern mobility analytics interface that clearly shows:
Where advertising vehicles actually operate in the city.
The platform should feel like a mobility data platform, not just an ad marketplace.
Очень важная рекомендация (как UX специалист)
Когда дизайнер будет делать интерфейс, самый главный экран должен выглядеть примерно так:
--------------------------------------
| Filters | Campaign | Vehicle | Date |
--------------------------------------

           CITY MAP
        HEATMAP LAYER

--------------------------------------
| impressions | parking | driving km |
--------------------------------------
То есть map-first interface.
Это сильно повышает ценность продукта.