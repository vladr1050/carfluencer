# Entity Relationships

This document describes the relationships between the core domain entities in the Carfluencer MVP.

The goal is to define how entities interact and how they should be represented in the database and application logic.

---

# Relationship Overview

MediaOwner
    ↓ owns
Vehicle
    ↓ has
Device

Advertiser
    ↓ runs
Campaign
    ↓ includes
CampaignVehicle
    ↓ references
Vehicle

Vehicle
    ↓ generates
DeviceLocations
    ↓ used for analytics
StopSessions
    ↓ attributed to
GeoZones
    ↓ aggregated into
DailyImpressions
DailyZoneImpressions

---

# Entity Relationship Details

## MediaOwner → Vehicles

One media owner can own multiple vehicles.

Relationship:

MediaOwner (1) → (N) Vehicles

Database:

vehicles.media_owner_id → media_owners.id

Telemetry sync (admin / scheduler UX), on `vehicles`:

- `telemetry_pull_enabled` — include IMEI in platform scheduler incremental pull.
- `telemetry_last_incremental_at`, `telemetry_last_historical_at`, `telemetry_last_success_at` — last successful job completion times (UTC in DB; UI may show app timezone).
- `telemetry_last_error` — last failure message from ClickHouse → PostgreSQL sync (cleared on success).

Technical incremental position per IMEI remains in `telemetry_sync_cursors` (`clickhouse:imei:{imei}:incremental`).

---

## Vehicle → Device

Each vehicle can have one telemetry device.

Relationship:

Vehicle (1) → (1) Device

Database:

devices.vehicle_id → vehicles.id

Note:
Future versions may support multiple devices per vehicle.

---

## Device → DeviceLocations

Each telemetry device generates many GPS records.

Relationship:

Device (1) → (N) DeviceLocations

Database:

device_locations.device_id → devices.id

---

## Advertiser → Campaigns

One advertiser can run multiple campaigns.

Relationship:

Advertiser (1) → (N) Campaigns

Database:

campaigns.advertiser_id → advertisers.id

---

## Campaign → Vehicles

A campaign can include multiple vehicles.

A vehicle can participate in multiple campaigns.

Relationship:

Campaign (N) ↔ (N) Vehicles

Database pivot table:

campaign_vehicles

Fields:

campaign_id  
vehicle_id

---

## Vehicle → Earnings

Vehicles generate revenue when they participate in campaigns.

Relationship:

Vehicle (1) → (N) VehicleEarnings

Database:

vehicle_earnings.vehicle_id → vehicles.id

vehicle_earnings.campaign_id → campaigns.id

---

# Telemetry Analytics Relationships

Telemetry data generates analytics layers.

---

## DeviceLocations → StopSessions

Raw telemetry points are grouped into stop sessions.

Relationship:

DeviceLocations (N) → StopSession (1)

---

## StopSessions → GeoZones

Each stop session can belong to a geozone.

Relationship:

StopSession (N) → GeoZone (1)

---

## DeviceLocations / StopSessions → Impressions

Movement and parking generate advertising impressions.

Movement impressions:

distance × coefficient

Parking impressions:

minutes × coefficient × zone multiplier

---

# Aggregation Tables

## DailyImpressions

Aggregated impressions per vehicle per day.

Fields:

vehicle_id  
date  
impressions  
distance_km  
parking_minutes

---

## DailyZoneImpressions

Aggregated impressions per vehicle per zone per day.

Fields:

vehicle_id  
zone_id  
date  
impressions

---

# Entity Diagram (Conceptual)

MediaOwner
    ↓
Vehicles
    ↓
Devices
    ↓
DeviceLocations
    ↓
StopSessions
    ↓
GeoZones
    ↓
Impressions

Advertiser
    ↓
Campaign
    ↓
CampaignVehicles
    ↓
Vehicles