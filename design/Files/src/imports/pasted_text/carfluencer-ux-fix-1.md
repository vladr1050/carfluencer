# Carfluencer UX/UI — FINAL BLOCKER FIX TASK

## Context

This task is based on FINAL QA verification.

Current status:

❌ NOT READY FOR DEVELOPMENT

Reason:

Critical mismatches between:

- UX design
- actual implementation
- acceptance criteria

---

## Your Goal

Resolve ALL BLOCKERS.

Design must become:

✅ unambiguous  
✅ implementation-ready  
✅ aligned with real system behavior  

---

# 🔴 BLOCK 1 — HEATMAP CONTEXT (CRITICAL)

---

## Problem

Heatmap context is inconsistent.

Different parts of system use different query parameters:

- from / to
- dateFrom / dateTo

Campaign name not always passed.

---

## FIX

You MUST define **single unified URL contract**

---

## FINAL STANDARD

```plaintext
/advertiser/heatmap?
campaignId=ID
&campaignName=STRING
&dateFrom=YYYY-MM-DD
&dateTo=YYYY-MM-DD
REQUIREMENTS
1. ALL links must use SAME params
Fix:
AdvertiserCampaigns
AdvertiserCampaignDetails
ANY entry point
2. Heatmap must:
read ALL params
pre-fill filters
show context banner
3. Date filter MUST reflect URL
❌ No default override (last-7-days)
DELIVERABLE
Figma must show:
entry from campaign
applied filters
correct banner
🔴 BLOCK 2 — REMOVE WRONG DATA FALLBACK
Problem
MediaOwner detail screens show incorrect data:
mock[id] || mock[1]
FIX
UX MUST define:
VALID STATES
Loading
Not Found
Error
STRICT RULE
❌ NEVER show wrong data
DELIVERABLE
Explicit UI for:
not found entity
empty campaign
empty vehicle
🔴 BLOCK 3 — HEATMAP LIFECYCLE UX
Problem
Heatmap behavior is undefined during loading / resize.
FIX
Define UX states:
1. Initial loading
Loading map data...
2. Filter change
show loading
update map
3. No data
No data for selected period
DELIVERABLE
State transitions clearly visualized
🔴 BLOCK 4 — SCREEN STATES (GLOBAL)
Problem
Not all screens define states.
FIX
ALL screens MUST include:
1. Loading state
Skeleton or loader
2. Empty state
No data available
3. Error state
Something went wrong
SCREENS:
Dashboard
Vehicles
Campaigns
Earnings
Heatmap
DELIVERABLE
Design all states explicitly
🔴 BLOCK 5 — FIX BROKEN FLOW
Problem
Campaign List → Heatmap flow broken
FIX
Define exact flow:
User clicks:
"View Heatmap"
System:
navigates with full context
opens heatmap with applied filters
DELIVERABLE
Full flow diagram in Figma
🔴 BLOCK 6 — KPI SOURCE CLARITY
Problem
KPI logic unclear.
FIX
Define:
Heatmap KPIs:
impressions
driving distance
driving time
parking time
MUST DEFINE:
source (API)
dependency on filters
update behavior
DELIVERABLE
UX annotations:
Where data comes from
When it updates
🟠 BLOCK 7 — REMOVE MVP CONFUSION
FIX:
Simplify Advertiser Campaign UI
REMOVE or SIMPLIFY:
Budget
Spent
Progress %
KEEP:
campaign name
dates
vehicles
heatmap link
DELIVERABLE
Clean MVP-aligned UI
🟡 BLOCK 8 — BRAND SYSTEM (STRICT MODE)
Problem
Current design violates "ONLY 5 COLORS"
FIX (choose ONE and enforce)
Option A — STRICT BRAND
Use ONLY:
#C1F60D
#F10DBF
#000000
#545454
#FFFFFF
Option B — EXTENDED SYSTEM (RECOMMENDED)
Define:
brand colors (primary)
neutral scale (gray system)
semantic colors:
success
warning
error
MUST:
document rules
apply consistently
Typography
Define final font (Helios or fallback)
DELIVERABLE
Final design tokens system
🟡 BLOCK 9 — DATA CONSISTENCY
Problem
Lists and detail screens inconsistent
FIX
Define:
same dataset logic
no contradictions
DELIVERABLE
Consistent UX behavior across:
lists
detail pages
🧠 CORE RULE
UX must reflect REAL SYSTEM.
NOT:
❌ mock behavior
BUT:
✅ real logic
📦 FINAL DELIVERABLE
Figma must include:
1. Fixed Heatmap UX
unified query
correct prefill
correct states
2. Correct Data States
loading
empty
error
3. Fixed Navigation Flow
4. Clean MVP UI
5. Documented behavior
DONE WHEN
Developer can:
implement WITHOUT questions
follow exact behavior
trust all flows
FAILURE IF
ANY of these exists:
inconsistent URL params
wrong data fallback
missing states
unclear behavior