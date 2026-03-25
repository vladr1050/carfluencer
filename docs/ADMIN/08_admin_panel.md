# Admin Panel

Built using:

Laravel Filament

URL:

/admin

---

## Features

### Media Owners

Create  
Edit  
View vehicles

---

### Advertisers

Create  
Edit  
Assign discounts

---

### Vehicles

Manage inventory

**Telemetry (ClickHouse → PostgreSQL)** — on each vehicle:

- **Scheduled ClickHouse pull** (edit form): when **on**, the vehicle’s IMEI is included in the platform **scheduler** incremental tick; when **off**, that vehicle is skipped by the tick (manual sync still works).
- **View** page: last incremental / historical / any successful pull, last error.
- **List**: columns **Sched. pull**, **Last CH sync**, optional **Sync error**, filter by scheduled pull.

Row action **ClickHouse** (dropdown): incremental pull or historical backfill for that IMEI.

Toolbar bulk **ClickHouse sync**: one modal — new data only or date range — for all selected vehicles.

---

### Telematics (sidebar group at the bottom, same pattern as Campaigns / Fleet)

Under **Telematics** in the sidebar (collapsed group like the others):

- **Heatmap** — objects, period, point type, **Load / refresh heatmap**, map (PostgreSQL `device_locations`).
- **ClickHouse & automation** — queue **ClickHouse → PostgreSQL** (incremental or historical) + collapsed **scheduler**.

The scheduler runs `php artisan telemetry:scheduler-tick` **hourly** by default (system cron `schedule:run` once per hour); incremental pull uses IMEIs for vehicles with **Scheduled ClickHouse pull** enabled (and a non-empty IMEI). For a shorter incremental interval, run `schedule:run` every minute and use `everyMinute()` in `bootstrap/app.php`.

> Ops note: `php artisan telemetry:sync-incremental` is still a **global** ClickHouse cursor (entire table). The scheduler tick does not use it for normal operation.

Per-vehicle or custom multi-select remains under **Fleet → Vehicles** (ClickHouse actions). Campaign-wide sync also from **View campaign** header actions (and campaign infolist shows an aggregate telemetry line for linked vehicles).

After a **historical** backfill, the per-IMEI incremental cursor is advanced so the next incremental run does not re-scan the full ClickHouse history (PostgreSQL upsert stays idempotent).

---

### Devices

Add IMEI

Configure sync

Enable telemetry collection

---

### Campaigns

Create campaign

Assign advertiser

Assign vehicles

On **View campaign**, telemetry header actions sync **all** vehicles linked via `campaign_vehicles`. The infolist **Linked vehicles (telemetry)** reminds that campaign-level sync is not limited to a single truck.

---

### Pricing

Set base advertising price per vehicle

---

### Discounts

Assign individual advertiser discount