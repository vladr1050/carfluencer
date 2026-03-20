# System Architecture

Frontend

MediaOwner Portal → React

Advertiser Portal → React

Admin Panel → Filament

Backend

Laravel API

Database

PostgreSQL

Telemetry

ClickHouse

Infrastructure

Hetzner Cloud

---

# System Diagram

ClickHouse
    ↓
Telemetry Collector (Laravel)
    ↓
PostgreSQL
    ↓
API
    ↓
Admin / MediaOwner / Advertiser