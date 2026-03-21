import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { apiJson, storageUrl } from '@/lib/api';

type Vehicle = {
  id: number;
  brand: string;
  model: string;
  imei: string;
  year: number | null;
  color: string | null;
  status: string;
  notes: string | null;
  image_path?: string | null;
};

export function MediaOwnerVehicleDetails() {
  const { id } = useParams();
  const [v, setV] = useState<Vehicle | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    apiJson<Vehicle>(`/api/media-owner/vehicles/${id}`)
      .then(setV)
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) {
    return (
      <div className="p-8 flex items-center gap-3">
        <Loader2 className="w-8 h-8 animate-spin" />
      </div>
    );
  }

  if (error || !v) {
    return (
      <div className="p-8">
        <Link to="/media-owner/vehicles" className="text-sm text-muted-foreground">
          ← Back
        </Link>
        <p className="text-destructive mt-4">{error ?? 'Not found'}</p>
      </div>
    );
  }

  const img = storageUrl(v.image_path);

  return (
    <div className="p-8">
      <Link to="/media-owner/vehicles" className="inline-flex items-center gap-2 text-sm text-muted-foreground mb-6 hover:text-foreground">
        <ArrowLeft className="w-4 h-4" />
        Back to vehicles
      </Link>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl">
        <div className="aspect-video rounded-lg border border-border bg-muted overflow-hidden flex items-center justify-center">
          {img ? <img src={img} alt="" className="w-full h-full object-cover" /> : <span className="text-muted-foreground text-sm">No image</span>}
        </div>
        <div>
          <h1 className="text-3xl mb-2">
            {v.brand} {v.model}
          </h1>
          <p className="font-mono text-sm text-muted-foreground mb-4">{v.imei}</p>
          <ul className="space-y-2 text-sm">
            <li>
              <span className="text-muted-foreground">Year:</span> {v.year ?? '—'}
            </li>
            <li>
              <span className="text-muted-foreground">Color:</span> {v.color ?? '—'}
            </li>
            <li>
              <span className="text-muted-foreground">Status:</span> {v.status}
            </li>
            {v.notes ? (
              <li>
                <span className="text-muted-foreground">Notes:</span> {v.notes}
              </li>
            ) : null}
          </ul>
        </div>
      </div>
    </div>
  );
}
