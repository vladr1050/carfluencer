import { apiJson } from './api';

export type VehicleFieldOption = { key: string; label: string };

export type VehicleMetaResponse = {
  colors: VehicleFieldOption[];
  fleet_statuses: VehicleFieldOption[];
  catalog_statuses: string[];
};

/** Colors + fleet status labels from Laravel (Sanctum). */
export async function fetchVehicleMeta(): Promise<VehicleMetaResponse> {
  return apiJson<VehicleMetaResponse>('/api/meta/vehicle-fields');
}

export function optionLabel(
  options: VehicleFieldOption[],
  key: string | null | undefined,
  fallback = '—'
): string {
  if (key == null || key === '') {
    return fallback;
  }
  return options.find((o) => o.key === key)?.label ?? key.replace(/_/g, ' ');
}
