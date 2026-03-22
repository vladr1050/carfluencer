import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router';
import { ArrowLeft, Loader2, AlertCircle } from 'lucide-react';
import { apiJson, storageUrl } from '@/lib/api';

type CampaignBrief = {
  id: number;
  name: string;
  status: string;
  start_date?: string | null;
  end_date?: string | null;
};

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  year: number | null;
  color_key?: string | null;
  color_label?: string | null;
  status: string;
  status_label?: string;
  image_path?: string | null;
  campaigns?: CampaignBrief[];
  media_owner?: { name: string; company_name: string | null } | null;
};

export function AdvertiserVehicleDetails() {
  const { id } = useParams();
  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    apiJson<Vehicle>(`/api/advertiser/vehicles/${id}`)
      .then(setVehicle)
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
        <span className="text-muted-foreground">Loading…</span>
      </div>
    );
  }

  if (error || !vehicle) {
    return (
      <div className="p-8">
        <Link to="/advertiser/vehicles" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6">
          <ArrowLeft className="w-4 h-4" />
          Back
        </Link>
        <div className="flex flex-col items-center justify-center min-h-[40vh] text-center">
          <AlertCircle className="w-14 h-14 mb-4 text-muted-foreground" />
          <p className="text-destructive">{error ?? 'Not found'}</p>
        </div>
      </div>
    );
  }

  const img = storageUrl(vehicle.image_path);

  return (
    <div className="p-8">
      <Link to="/advertiser/vehicles" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6 hover:text-foreground">
        <ArrowLeft className="w-4 h-4" />
        Back to vehicles
      </Link>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl">
        <div className="aspect-video rounded-lg border border-border bg-muted overflow-hidden flex items-center justify-center">
          {img ? (
            <img src={img} alt="" className="w-full h-full object-cover" />
          ) : (
            <span className="text-muted-foreground text-sm">No image</span>
          )}
        </div>
        <div>
          <h1 className="text-3xl mb-2">
            {vehicle.brand} {vehicle.model}
          </h1>
          <p className="font-mono text-sm text-muted-foreground mb-4">{vehicle.imei}</p>
          <ul className="space-y-2 text-sm">
            <li>
              <span className="text-muted-foreground">Year:</span> {vehicle.year ?? '—'}
            </li>
            <li>
              <span className="text-muted-foreground">Color:</span> {vehicle.color_label ?? '—'}
            </li>
            <li>
              <span className="text-muted-foreground">Status:</span> {vehicle.status_label ?? vehicle.status}
            </li>
            {vehicle.media_owner ? (
              <li>
                <span className="text-muted-foreground">Media owner:</span>{' '}
                {vehicle.media_owner.company_name || vehicle.media_owner.name}
              </li>
            ) : null}
          </ul>

          {vehicle.campaigns && vehicle.campaigns.length > 0 ? (
            <div className="mt-6">
              <h2 className="text-sm font-semibold mb-2">Your campaigns using this vehicle</h2>
              <ul className="text-sm space-y-1 border border-border rounded-lg p-3 bg-card">
                {vehicle.campaigns.map((c) => (
                  <li key={c.id}>
                    <Link to={`/advertiser/campaigns/${c.id}`} className="text-primary hover:underline">
                      {c.name}
                    </Link>
                    <span className="text-muted-foreground text-xs ml-2 capitalize">({c.status})</span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
