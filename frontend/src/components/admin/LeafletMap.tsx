"use client";

import { useEffect, useRef } from "react";
import L from "leaflet";

interface LeafletMapProps {
  lat: number;
  lng: number;
  sppgLat?: number;
  sppgLng?: number;
  radiusMeters?: number;
}

export default function LeafletMap({ lat, lng, sppgLat = -7.96662040, sppgLng = 112.63263210, radiusMeters = 50 }: LeafletMapProps) {
  const mapRef = useRef<HTMLDivElement | null>(null);
  const mapInstanceRef = useRef<L.Map | null>(null);

  useEffect(() => {
    if (!mapRef.current) return;
    if (typeof window === "undefined") return;

    // Leaflet dimuat hanya pada client karena komponen memakai dynamic import dengan SSR nonaktif.

    if (mapInstanceRef.current) {
      mapInstanceRef.current.remove();
    }

    const map = L.map(mapRef.current).setView([lat, lng], 17);
    mapInstanceRef.current = map;

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    // Marker SPPG Location Center & Radius Circle
    L.circle([sppgLat, sppgLng], {
      color: "#059669",
      fillColor: "#10b981",
      fillOpacity: 0.2,
      radius: radiusMeters,
    }).addTo(map);

    L.marker([sppgLat, sppgLng])
      .addTo(map)
      .bindPopup("<b>Unit SPPG Pakiskembar 2</b><br>Titik Pusat Geofence Resmi");

    // Marker Employee Position
    L.marker([lat, lng])
      .addTo(map)
      .bindPopup(`<b>Lokasi Presensi Pegawai</b><br>Lat: ${lat}, Lng: ${lng}`)
      .openPopup();

    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove();
        mapInstanceRef.current = null;
      }
    };
  }, [lat, lng, sppgLat, sppgLng, radiusMeters]);

  return <div ref={mapRef} className="w-full h-64 rounded-2xl shadow-inner border border-slate-200" />;
}
