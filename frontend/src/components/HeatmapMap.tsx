import L from 'leaflet';
import { useEffect } from 'react';
import { CircleMarker, MapContainer, Popup, TileLayer, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';

export type HeatmapPoint = { lat: number; lng: number; intensity: number };

function FitBounds({ points }: { points: HeatmapPoint[] }): null {
  const map = useMap();

  useEffect(() => {
    if (points.length === 0) {
      return;
    }
    const bounds = L.latLngBounds(points.map((p) => [p.lat, p.lng] as [number, number]));
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
  }, [map, points]);

  return null;
}

export function HeatmapMap({ points }: { points: HeatmapPoint[] }): JSX.Element {
  const defaultCenter: [number, number] = [51.505, -0.09];
  const center: [number, number] =
    points.length > 0 ? [points[0].lat, points[0].lng] : defaultCenter;

  return (
    <div className="heatmap-map-wrap">
      <MapContainer center={center} zoom={12} scrollWheelZoom style={{ height: 400, width: '100%', borderRadius: 8 }}>
        <TileLayer attribution="&copy; OpenStreetMap contributors" url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
        <FitBounds points={points} />
        {points.map((p, i) => (
          <CircleMarker
            key={`${p.lat}-${p.lng}-${i}`}
            center={[p.lat, p.lng]}
            radius={6 + p.intensity * 16}
            pathOptions={{
              color: '#F10DBF',
              fillColor: '#C1F60D',
              fillOpacity: 0.35 + p.intensity * 0.45,
              weight: 2,
            }}
          >
            <Popup>
              <strong>Intensity</strong> {p.intensity.toFixed(2)}
              <br />
              {p.lat.toFixed(5)}, {p.lng.toFixed(5)}
            </Popup>
          </CircleMarker>
        ))}
      </MapContainer>
    </div>
  );
}
