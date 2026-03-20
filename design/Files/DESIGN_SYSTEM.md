# CARFLUENCER x evo.ad Design System

## BLOCK 8 FIX: Brand System Documentation

### Color System

The platform uses an **extended semantic color system** with strict brand colors and functional semantic colors.

#### Brand Colors (Primary)
- **Neon Green**: `#C1F60D` - Primary brand color, used for:
  - Primary buttons and CTAs
  - Active campaign status
  - Driving heatmap visualization
  - Key metrics and positive indicators
  - Progress bars

- **Magenta**: `#F10DBF` - Secondary brand color, used for:
  - Accent elements
  - Parking heatmap visualization
  - Secondary actions
  - Campaign status badges

- **Black**: `#000000` - Used for:
  - Text on light backgrounds
  - High contrast UI elements
  - Heatmap low-intensity gradient start

- **Gray**: `#545454` - Used for:
  - Muted text
  - Secondary UI elements
  - Disabled states

- **White**: `#FFFFFF` - Used for:
  - Light theme backgrounds
  - Text on dark backgrounds
  - Heatmap high-intensity gradient end

#### Semantic Colors (Functional)
- **Success**: `#C1F60D` (maps to brand green)
- **Warning**: `#FF9500` (amber for warnings)
- **Error**: `#FF3B30` (red for errors)
- **Info**: `#007AFF` (blue for informational messages)

#### Neutral Scale (Gray System)
- Background: CSS variable `--color-background`
- Card: CSS variable `--color-card`
- Border: CSS variable `--color-border`
- Muted: CSS variable `--color-muted`
- Foreground: CSS variable `--color-foreground`
- Muted Foreground: CSS variable `--color-muted-foreground`

### Typography

**Primary Font**: HELIOS (brand font)
**Fallback**: Inter (system font)

Font loading priority:
1. HELIOS (if available via fonts.css)
2. Inter (Google Fonts fallback)
3. System sans-serif

#### Font Sizes
Defined in `/src/styles/theme.css`:
- Headings: Use default theme styles (h1, h2, h3)
- Body: Base font size from theme
- Small text: 0.875rem (14px)
- Tiny text: 0.75rem (12px)

### Component Guidelines

#### Buttons
- **Primary**: `bg-primary text-primary-foreground` (Neon Green)
- **Secondary**: `bg-secondary text-secondary-foreground`
- **Accent**: `bg-accent text-accent-foreground` (Magenta)
- **Muted**: `bg-muted text-foreground`

#### Status Badges
- **Active**: Green background with green text
- **Completed**: Gray background with gray text
- **Upcoming**: Accent background with accent text
- **Error**: Red background with white text

#### Cards
- Background: `bg-card`
- Border: `border border-border`
- Hover: `hover:shadow-lg transition-shadow`

### Theme Support

The platform supports both **light** and **dark** themes:
- Theme switching via toggle in header
- All colors use CSS variables for automatic theme adaptation
- Dark theme uses inverted color scheme while maintaining brand colors

### Heatmap Visualization

#### Color Gradients
**Driving Mode**: 
- Low to High: `#000000 → #C1F60D → #FFFFFF`
- Represents vehicle driving exposure density

**Parking Mode**: 
- Low to High: `#000000 → #F10DBF → #FFFFFF`
- Represents vehicle parking exposure density

**Both Mode**: 
- Combined: `#000000 → #C1F60D → #F10DBF → #FFFFFF`
- Shows both driving and parking data

### Accessibility

- All colors meet WCAG AA contrast requirements
- Focus states use `focus:ring-2 focus:ring-primary/20`
- Keyboard navigation supported throughout
- Screen reader friendly labels

---

## BLOCK 6: Heatmap Behavior Documentation

### URL Context Application
- **URL Parameters**: `?campaignId=ID&campaignName=STRING&dateFrom=YYYY-MM-DD&dateTo=YYYY-MM-DD&vehicle=all`
- **Behavior**: When navigating from campaign, URL params auto-fill filters
- **Date Range**: If `dateFrom` and `dateTo` in URL, selector shows "Custom Range" with visible dates

### Map Reload Triggers
Map reloads when:
1. **Mode changes** (Driving, Parking, Both)
2. **Vehicle filter changes**
3. **Date range changes**
4. **Custom date inputs change**

All reloads show loading overlay: "Loading map data..."

### Reset Behavior
**Reset Filters button** clears:
- Campaign context (removes URL params)
- Vehicle selection → "All Vehicles"
- Date range → "Last 7 Days"
- Custom dates → cleared
- Mode → "Both"

### Empty Data State
When no telemetry data available:
- Shows overlay with message: "No telemetry data"
- Description: "No data available for the selected period"
- Provides guidance: "Try changing date range or filters"
- Offers "Reset Filters" action
- Visual: Dimmed map with backdrop-blur-sm (z-index: 1500)

**Demo Toggle**: Use "Show Empty State" button in Heatmap header to demonstrate this state

### Loading States
1. **Initial Load**: Shows loading overlay until map canvas ready
2. **Filter Change**: Shows loading overlay during data fetch simulation
3. **Minimum Duration**: 300ms to prevent flickering
4. **Visual**: Backdrop blur with spinner (z-index: 2000)

### State Transitions (Micro Polish)
**Flow Diagram:**
```
1. Initial Load → Loading (300ms) → Map with Data
2. Filter Change → Loading (300ms) → Updated Data
3. No Data → Empty State (dimmed background)
4. Reset → Loading → Default State
```

**Z-Index Hierarchy:**
```
Loading State:  z-[2000] (highest priority)
Empty State:    z-[1500] (medium priority)
Map Content:    z-0      (base layer)
```

**State Priority:**
- Loading always shows above Empty
- Empty only shows when NOT loading
- Map always visible in background

### Context Banner
- **Visibility**: Only shown when `campaignId` exists in URL
- **Content**: Campaign name, date range, vehicle selection
- **Action**: "Clear Context" button → triggers reset

### Legend Behavior
- **Dynamic**: Changes based on selected mode
- **Parking**: Shows magenta color indicator
- **Driving**: Shows green color indicator
- **Both**: Shows both color indicators
- **Gradient**: Updates to match mode visualization

---

Last Updated: March 20, 2026

---

## FINAL POLISH: Global Screen States

### Loading States
All major screens include consistent loading states:

**Visual Pattern**:
```
- Centered container
- Spinner icon (Loader2, 12x12, animated)
- Message: "Loading [resource]..."
- Minimum duration: 300ms (prevents flickering)
```

**Screens with Loading**:
- Campaigns list
- Vehicles list  
- Heatmap data
- Dashboard (on initial load)

---

### Empty States
All major screens include friendly empty states:

**Visual Pattern**:
```
- Centered card with border
- Large icon (16x16, muted color)
- Heading: "No [Resource] Yet"
- Description text
- Primary CTA button
```

**Examples**:
- **No Campaigns**: "You haven't created any campaigns yet. Create your first campaign to start advertising."
- **No Vehicles**: "You haven't added any vehicles to your fleet yet. Add your first vehicle to start earning."
- **No Telemetry Data**: "No telemetry data found for the selected period and filters."

---

### Error States
All major screens include error recovery:

**Visual Pattern**:
```
- Centered card with border
- AlertCircle icon (16x16, destructive color)
- Heading: "Failed to Load [Resource]"
- Description: "Unable to fetch [resource]. Please try again."
- Retry button (primary style)
```

**User Actions**:
- Retry button clears error and attempts reload
- Error state preserves user's context

---

### Not Found States
All detail pages include Not Found states:

**Visual Pattern**:
```
- Back navigation link at top
- Centered card
- AlertCircle icon (16x16, muted)
- Heading: "[Resource] Not Found"
- Description with guidance
- CTA to return to list
```

**Examples**:
- Campaign Not Found → "View All Campaigns"
- Vehicle Not Found → "View All Vehicles"

---

## Icons Reference

### Semantic Icon Usage

**Primary Icons**:
- **Briefcase**: Campaigns, business
- **Car**: Vehicles, fleet
- **Eye**: Impressions, views
- **Navigation**: Driving distance, routes
- **Clock**: Time metrics (driving, parking)
- **MapPin**: Location, heatmap
- **Calendar**: Dates, scheduling
- **Loader2**: Loading states (animated)
- **AlertCircle**: Errors, warnings, not found

**Action Icons**:
- **Plus**: Add new items
- **Edit**: Edit/modify
- **Eye**: View details
- **MoreVertical**: More options menu
- **ArrowLeft**: Back navigation
- **X**: Close, clear
- **ChevronDown/Up**: Expand/collapse
- **RotateCcw**: Reset, refresh

**Status Indication**:
- **Green (primary)**: Active, success
- **Magenta (accent)**: Secondary emphasis
- **Gray**: Inactive, muted
- **Red**: Error, destructive

---

Last Updated: March 20, 2026 (Final Polish Complete)