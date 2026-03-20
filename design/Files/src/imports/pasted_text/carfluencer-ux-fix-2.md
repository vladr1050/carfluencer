# Carfluencer UX/UI — Fix Task After Verification Round 2

## Context

The design was reviewed again after previous fixes.

Current status:

❌ NOT READY

The latest verification found several remaining blockers that must be fixed before development can start.

This task is focused on:

- fixing remaining UX issues
- clarifying missing states
- aligning dashboards and heatmap
- updating MediaOwner vehicle creation flow
- improving implementation readiness

---

## Goal

Make the design fully ready for development by resolving all remaining blockers from Round 2.

---

# 🔴 BLOCK 1 — MEDIA OWNER: ADD IMEI FIELD

## New Requirement

When a MediaOwner adds a new vehicle/object, the form must include a new field:

### IMEI

This field is required for telemetry integration.

---

## Update Add Vehicle Flow

The Add Vehicle form for MediaOwner must include:

- Brand
- Model
- Year
- Color
- Quantity
- Vehicle image
- IMEI

---

## IMEI Field UX Requirements

Define clearly:

- label: IMEI
- placeholder example: `123456789012345`
- validation hint: numeric / 15 digits (or “IMEI format” if exact validation is handled in backend)
- helper text:
  `Used to connect the vehicle to telemetry data collection`

---

## Deliverable

Update:

- Add Vehicle modal/page
- field order
- validation state
- error state for invalid IMEI

---

# 🔴 BLOCK 2 — GLOBAL SCREEN STATES

## Problem

Not all screens have clear loading / empty / error states.

---

## Fix

Design explicit states for ALL main screens:

### Advertiser
- Dashboard
- Vehicles
- Campaigns
- Heatmap

### MediaOwner
- Dashboard
- Vehicles
- Campaigns
- Earnings

---

## Required states per screen

### Loading
- skeletons or clear loader

### Empty
Examples:
- No vehicles yet
- No campaigns yet
- No earnings yet
- No telemetry data for selected period

### Error
Examples:
- Failed to load dashboard
- Failed to load vehicles
- Failed to load heatmap data

---

## Deliverable

Each major screen must have:
- default state
- loading state
- empty state
- error state

---

# 🔴 BLOCK 3 — HEATMAP DATE RANGE UX

## Problem

Heatmap supports URL dateFrom/dateTo, but the Date Range selector UX is inconsistent.

There is a “custom” state in logic, but it is not properly represented in UI.

---

## Fix

You must define ONE clear UX approach.

### Recommended approach:

Date Range select options:

- Last 24 hours
- Last 7 days
- Last 30 days
- Custom range

If user enters from campaign context with dateFrom/dateTo:

- selector shows `Custom range`
- custom dates are visible
- context is clearly reflected in UI

---

## Also define Reset behavior

When user clicks Reset Filters:

- campaign context is cleared or preserved — choose one behavior and document it
- date range resets clearly
- custom dates are cleared

---

## Deliverable

Update heatmap filter UX with:
- proper custom range option
- visible selected custom dates
- reset behavior

---

# 🔴 BLOCK 4 — HEATMAP EMPTY DATA STATE

## Problem

There is no clear UX for empty telemetry result.

---

## Fix

Design a proper empty heatmap state:

### Example message:
`No telemetry data for the selected period`

Optional support text:
`Try another date range or vehicle`

Optional CTA:
- Reset filters
- Change date range

---

## Deliverable

Add explicit empty-data heatmap screen/state.

---

# 🔴 BLOCK 5 — ADVERTISER DASHBOARD KPI FIX

## Problem

Advertiser Dashboard still misses one required KPI:

- parking time

---

## Fix

Advertiser Dashboard must include these 4 metrics:

- impressions
- driving distance
- driving time
- parking time

---

## Requirement

Rebuild KPI cards so the hierarchy remains clean and readable.

If needed, reduce emphasis on less important cards and focus on telemetry-related metrics.

---

## Deliverable

Updated Advertiser Dashboard KPI block.

---

# 🔴 BLOCK 6 — HEATMAP BEHAVIOR ANNOTATIONS

## Problem

Some heatmap behavior is still ambiguous for development.

---

## Fix

Add small UX annotations in Figma for:

- how URL context is applied
- when map reloads
- what Reset does
- what happens if no data is returned

---

## Deliverable

Developer-facing behavior notes inside Figma or in design handoff comments.

---

# 🟠 BLOCK 7 — DATA CONSISTENCY IN PROTOTYPE

## Problem

Lists and detail screens are still inconsistent in prototype data.

---

## Fix

Align visible prototype/demo data so that:

- list items actually open existing detail screens
- no contradictory IDs in prototype flow
- no “Not Found” in normal happy-path demo unless intentionally testing edge case

---

## Deliverable

Prototype flow consistency between:
- campaign list → campaign details
- vehicle list → vehicle details

---

# 🟡 BLOCK 8 — BRAND SYSTEM CLARITY

## Problem

Current design still does not clearly define whether brand system is:

- strict 5 colors only
OR
- extended semantic system

---

## Fix

Choose and document one approach.

### Recommended:
Extended system with:

- brand colors
- neutrals
- semantic colors (success / warning / error)

But rules must be explicit.

---

## Also define typography

- Helios if available
OR
- approved fallback system

---

## Deliverable

Update design system page with:
- color rules
- semantic colors
- typography rules

---

# 🟡 BLOCK 9 — DEAD / UNCLEAR ACTIONS

## Fix

Review all remaining actions in MediaOwner and Advertiser screens.

Every action must do one of the following:

- navigate
- open modal
- submit
- reset
- upload

No dead icons or decorative controls without meaning.

---

## Deliverable

Clean action system.

---

# FINAL DELIVERABLE

Update Figma with:

1. IMEI field in MediaOwner Add Vehicle flow
2. Loading / empty / error states for all major screens
3. Fixed heatmap date range UX
4. Heatmap empty-data state
5. Advertiser Dashboard with parking time KPI
6. Behavior annotations for heatmap
7. Consistent prototype flow
8. Clear design system rules
9. No dead actions

---

## DONE WHEN

Design becomes:

- complete
- consistent
- ready for implementation
- clear for frontend development