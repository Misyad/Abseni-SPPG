"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Camera, CameraOff, RefreshCw, Check } from "lucide-react";

interface CameraViewfinderProps {
  onCapture: (blob: Blob) => void;
  onCancel: () => void;
}

export default function CameraViewfinder({ onCapture, onCancel }: CameraViewfinderProps) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);

  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [capturedBlob, setCapturedBlob] = useState<Blob | null>(null);
  const [capturedDataUrl, setCapturedDataUrl] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Stop all media stream tracks
  const stopStream = useCallback(() => {
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
  }, []);

  // Start Camera MediaStream
  const startCamera = useCallback(async () => {
    stopStream();
    setErrorMessage(null);
    setCapturedBlob(null);
    setCapturedDataUrl(null);

    try {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setHasPermission(false);
        setErrorMessage("Browser Anda tidak mendukung fitur akses kamera live.");
        return;
      }

      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: "user", // Kamera depan
          width: { ideal: 720 },
          height: { ideal: 720 },
        },
        audio: false,
      });

      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
      }
      setHasPermission(true);
    } catch (err: unknown) {
      setHasPermission(false);
      const errorName = err instanceof DOMException ? err.name : "";
      if (errorName === "NotAllowedError" || errorName === "PermissionDeniedError") {
        setErrorMessage("Izin akses kamera ditolak. Mohon beri izin akses kamera di pengaturan browser Anda.");
      } else {
        setErrorMessage("Kamera tidak ditemukan atau sedang digunakan oleh aplikasi lain.");
      }
    }
  }, [stopStream]);

  useEffect(() => {
    void startCamera();
    return stopStream;
  }, [startCamera, stopStream]);

  // Take Snapshot Frame
  const takeSnapshot = () => {
    if (!videoRef.current) return;

    const video = videoRef.current;
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 640;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    // Draw video frame to canvas
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(
      (blob) => {
        if (blob) {
          setCapturedBlob(blob);
          setCapturedDataUrl(canvas.toDataURL("image/jpeg", 0.85));
          stopStream(); // Hentikan stream kamera segera setelah foto diambil
        }
      },
      "image/jpeg",
      0.85
    );
  };

  // Retake photo
  const handleRetake = () => {
    void startCamera();
  };

  // Confirm photo
  const handleConfirm = () => {
    if (capturedBlob) {
      onCapture(capturedBlob);
    }
  };

  return (
    <div className="bg-white rounded-3xl p-5 shadow-md border border-slate-200 space-y-4 max-w-sm mx-auto">
      <div className="text-center space-y-1">
        <h2 className="text-base font-bold text-slate-800">Swafoto Kehadiran</h2>
        <p className="text-xs text-slate-500">Posisikan wajah Anda tepat di dalam area lingkaran</p>
      </div>

      {/* Viewfinder Container */}
      <div className="relative aspect-square w-full rounded-2xl overflow-hidden bg-slate-900 flex items-center justify-center border-2 border-slate-800 shadow-inner">
        {capturedDataUrl ? (
          // Captured Image Preview
          <img
            src={capturedDataUrl}
            alt="Preview Selfie"
            className="w-full h-full object-cover"
          />
        ) : hasPermission === true ? (
          // Live Video Stream with Face Oval Overlay
          <>
            <video
              ref={videoRef}
              autoPlay
              playsInline
              muted
              className="w-full h-full object-cover transform -scale-x-100"
            />
            {/* Oval Face Guide */}
            <div className="absolute inset-0 pointer-events-none flex items-center justify-center">
              <div className="w-56 h-72 border-2 border-dashed border-emerald-400/80 rounded-full shadow-[0_0_0_9999px_rgba(15,23,42,0.4)]" />
            </div>
            <div className="absolute top-3 left-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded-full text-[10px] text-emerald-400 font-semibold flex items-center space-x-1">
              <div className="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
              <span>Kamera Live</span>
            </div>
          </>
        ) : (
          // Permission Denied / Error State
          <div className="p-6 text-center text-slate-400 space-y-3">
            <CameraOff className="w-12 h-12 mx-auto text-rose-400" />
            <p className="text-xs text-rose-300 font-medium leading-relaxed">
              {errorMessage || "Kamera tidak dapat diakses."}
            </p>
            <button
              onClick={startCamera}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold"
            >
              Coba Lagi
            </button>
          </div>
        )}
      </div>

      {/* Control Action Buttons */}
      <div className="flex items-center justify-between gap-3 pt-2">
        <button
          onClick={() => {
            stopStream();
            onCancel();
          }}
          className="flex-1 py-3 px-4 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50 transition-colors"
        >
          Batal
        </button>

        {capturedDataUrl ? (
          <>
            <button
              onClick={handleRetake}
              className="py-3 px-4 rounded-xl bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition-colors flex items-center justify-center space-x-1"
            >
              <RefreshCw className="w-4 h-4" />
              <span>Ulangi</span>
            </button>
            <button
              onClick={handleConfirm}
              className="flex-1 py-3 px-4 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition-colors shadow-sm flex items-center justify-center space-x-1"
            >
              <Check className="w-4 h-4" />
              <span>Gunakan Foto</span>
            </button>
          </>
        ) : (
          <button
            onClick={takeSnapshot}
            disabled={!hasPermission}
            className="flex-1 py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all disabled:opacity-40 flex items-center justify-center space-x-1.5 shadow-sm"
          >
            <Camera className="w-4 h-4" />
            <span>Ambil Foto</span>
          </button>
        )}
      </div>
    </div>
  );
}
