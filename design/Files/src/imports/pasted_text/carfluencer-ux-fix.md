# Carfluencer UX/UI — Final Fix Task (After Verification)

## Context

You are updating the design AFTER engineering validation.

The current implementation exposed **critical gaps between UX and real behavior**.

Your goal:

Make the design **fully consistent with real product logic and implementation**.

---

## PRIORITY

Fix:

1. Product logic mismatches
2. Broken navigation concepts
3. Heatmap UX inconsistencies
4. Data integrity issues

---

# 🔴 BLOCK 1 — HEATMAP CONTEXT (CRITICAL)

---

## Problem

Heatmap does NOT reflect selected campaign and dates.

---

## Fix UX

You MUST introduce:

### 1. Context Banner (Top of Heatmap)

```plaintext
Campaign: [Campaign Name]
Date: [From → To]
Vehicles: [Selected / All]
2. Pre-filled Filters Behavior
Define UX rules:
If user comes from Campaign → filters are pre-filled
If user opens Heatmap directly → empty/default state
3. Filter State System
Filters must behave consistently:
controlled state
visible current selection
reset option
Deliverable
Design must clearly show:
how context is applied
how filters update
what happens on entry
🔴 BLOCK 2 — MEDIA OWNER HEATMAP (CRITICAL)
Problem
MediaOwner has link to Heatmap, but:
no route
not in MVP scope
Fix (choose ONE and implement clearly)
Option A (Recommended)
❌ REMOVE Heatmap from MediaOwner
MediaOwner focuses ONLY on:
earnings
vehicles
campaigns
Option B
Add:
Read-only Heatmap for MediaOwner
BUT:
no filters by advertiser
no campaign switching
only own vehicles
Deliverable
Update:
remove or redesign all "View Heatmap" links for MediaOwner
reflect correct navigation
🔴 BLOCK 3 — DETAIL SCREENS DATA CONSISTENCY
Problem
Detail pages show incorrect data due to ID mismatch.
Fix UX
Define:
1. Empty State
If data not found:
"Campaign not found"
"Vehicle not found"
2. Loading State
Skeleton UI or loader
3. No Fake Fallback
❌ DO NOT show wrong data
Deliverable
Add states:
loading
empty
error
🔴 BLOCK 4 — HEATMAP VISUAL CONSISTENCY
Problem
Legend does not match map mode.
Fix
Legend must change based on mode:
Driving
Green-based scale
Parking
Magenta-based scale
Both
Combined gradient or layered explanation
Deliverable
Dynamic legend UI
🔴 BLOCK 5 — HEATMAP UX STABILITY
Problem
Technical issues in implementation (layer duplication risk)
UX Fix
Define:
1. Loading State
"Loading map data..."
2. Refresh Behavior
When filters change:
show loading state
update map
3. No flickering / duplication UX
Deliverable
Clear UX for map updates
🟠 BLOCK 6 — DASHBOARD KPI ALIGNMENT
Fix KPI System
Advertiser Dashboard MUST include:
impressions
driving distance
driving time
parking time
MediaOwner Dashboard
Define clearly:
Option A:
Financial only
Option B:
Add mobility metrics
Deliverable
Consistent KPI logic
🟠 BLOCK 7 — REMOVE NON-MVP COMPLEXITY
Remove or simplify:
Budget
Spent
Progress %
Replace with:
campaign duration
vehicles
status
Deliverable
Clean MVP UI
🟡 BLOCK 8 — BRAND FIXES
Fix palette usage
Allowed ONLY:
#C1F60D
#F10DBF
#000000
#545454
#FFFFFF
Define:
Semantic Colors
Add controlled set:
success
warning
error
Typography
Define final font system:
Helios OR approved fallback
Deliverable
Clean design tokens
🟡 BLOCK 9 — ACTION CONSISTENCY
Fix dead actions:
Edit
More menu
icons without behavior
Rule:
Every button must:
navigate
OR
open modal
OR
trigger action
Deliverable
No dead UI
🧠 CORE PRODUCT PRINCIPLE
This is NOT:
❌ UI prototype
This IS:
✅ Product interface
User must always understand:
Where ads were seen
How vehicles moved
What value was generated
📦 FINAL DELIVERABLE
Update Figma:
1. Fixed Heatmap UX
context-aware
dynamic legend
stable behavior
2. Correct Navigation
no broken routes
no fake flows
3. Data States
loading
empty
error
4. Clean MVP UI
DONE WHEN
Design matches:
real implementation logic
API expectations
UX flows are complete
no ambiguity for developers