"use client";

import { useCallback, useEffect, useState } from "react";
import { Compass, MapPin, Navigation, AlertCircle } from "lucide-react";

interface GpsIndicatorProps {
  onLocationAcquired: (lat: number, lng: number, accuracy: number) => void;
  onError: (errorMsg: string) => void;
}

export default function GpsIndicator({ onLocationAcquired, onError }: GpsIndicatorProps) {
  const [status, setStatus] = useState<"loading" | "acquired" | "error">("loading");
  const [accuracy, setAccuracy] = useState<number | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const fetchGps = useCallback(() => {
    setStatus("loading");
    setErrorMessage(null);

    if (!navigator.geolocation) {
      setStatus("error");
      const msg = "Browser Anda tidak mendukung fitur Geolocation GPS.";
      setErrorMessage(msg);
      onError(msg);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const { latitude, longitude, accuracy: acc } = pos.coords;
        setAccuracy(Math.round(acc));
        setStatus("acquired");
        onLocationAcquired(latitude, longitude, acc);
      },
      (err) => {
        setStatus("error");
        let msg = "Gagal mengambil koordinat GPS.";
        if (err.code === err.PERMISSION_DENIED) {
          msg = "Izin akses lokasi ditolak. Mohon aktifkan izin GPS di browser Anda.";
        } else if (err.code === err.POSITION_UNAVAILABLE) {
          msg = "Sinyal lokasi tidak tersedia saat ini.";
        } else if (err.code === err.TIMEOUT) {
          msg = "Waktu pencarian sinyal GPS habis (Timeout).";
        }
        setErrorMessage(msg);
        onError(msg);
      },
      {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
      }
    );
  }, [onError, onLocationAcquired]);

  useEffect(() => {
    fetchGps();
  }, [fetchGps]);

  return (
    <div className="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-2 max-w-sm mx-auto">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-2">
          <MapPin className="w-4 h-4 text-emerald-600" />
          <span className="text-xs font-bold text-slate-700">Status Lokasi GPS</span>
        </div>

        {status === "loading" && (
          <span className="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center space-x-1">
            <Compass className="w-3 h-3 animate-spin text-amber-600" />
            <span>Mencari GPS...</span>
          </span>
        )}

        {status === "acquired" && (
          <span className="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center space-x-1">
            <Navigation className="w-3 h-3 text-emerald-600" />
            <span>Terkunci (±{accuracy}m)</span>
          </span>
        )}

        {status === "error" && (
          <span className="bg-rose-100 text-rose-800 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center space-x-1">
            <AlertCircle className="w-3 h-3 text-rose-600" />
            <span>Gagal Lokasi</span>
          </span>
        )}
      </div>

      {status === "error" && (
        <div className="text-[11px] text-rose-600 font-medium flex items-center justify-between pt-1">
          <span>{errorMessage}</span>
          <button
            onClick={fetchGps}
            className="text-emerald-700 underline font-semibold ml-2 text-xs"
          >
            Coba Lagi
          </button>
        </div>
      )}
    </div>
  );
}
