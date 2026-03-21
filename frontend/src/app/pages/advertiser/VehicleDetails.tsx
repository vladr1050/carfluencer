import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, Loader2, AlertCircle } from 'lucide-react';
import { apiJson, storageUrl } from '@/lib/api';

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  year: number | null;
  color: string | null;
  status: string;
  image_path?: string | null;
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
              <span className="text-muted-foreground">Color:</span> {vehicle.color ?? '—'}
            </li>
            <li>
              <span className="text-muted-foreground">Status:</span> {vehicle.status}
            </li>
          </ul>
        </div>
      </div>
    </div>
  );
}
