# Domain Entities

## MediaOwner

Vehicle owner.

Fields:

id  
name  
email  
company_name  
created_at

---

## Advertiser

Advertising client.

Fields:

id  
company_name  
email  
discount_percent

---

## Vehicle

Advertising object.

Fields:

id  
media_owner_id  
brand  
model  
year  
color  
image_url  
quantity

---

## Device

Telemetry device.

Fields:

id  
vehicle_id  
imei  
sync_enabled  
sync_interval

---

## Campaign

Advertising campaign.

Fields:

id  
advertiser_id  
name  
status  
start_date  
end_date

---

## CampaignVehicle

Vehicle participating in campaign.

Fields:

campaign_id  
vehicle_id

---

## VehicleEarnings

Income per vehicle.

Fields:

vehicle_id  
campaign_id  
amount