"use client";

import { useEffect, useState } from "react";
import Header from "@/components/employee/Header";
import EmployeeSelector from "@/components/employee/EmployeeSelector";
import PinPad from "@/components/employee/PinPad";
import CameraViewfinder from "@/components/employee/CameraViewfinder";
import GpsIndicator from "@/components/employee/GpsIndicator";
import AttendanceResultModal from "@/components/employee/AttendanceResultModal";
import { fetchPublicEmployees, submitAttendance, AttendanceSubmissionResponse, Division, EmployeeSimple } from "@/lib/api";
import { Loader2, AlertTriangle } from "lucide-react";

export default function HomePage() {
  const [divisions, setDivisions] = useState<Division[]>([]);
  const [loadingDivisions, setLoadingDivisions] = useState<boolean>(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Flow Step State
  const [selectedEmployee, setSelectedEmployee] = useState<EmployeeSimple | null>(null);
  const [verifiedPin, setVerifiedPin] = useState<string | null>(null);

  // GPS State
  const [gpsData, setGpsData] = useState<{ lat: number; lng: number; accuracy: number } | null>(null);

  // Submission State
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [attendanceResult, setAttendanceResult] = useState<AttendanceSubmissionResponse | null>(null);

  // Fetch Public Employees List
  const loadEmployees = async () => {
    setLoadingDivisions(true);
    setLoadError(null);
    try {
      const data = await fetchPublicEmployees();
      setDivisions(data);
    } catch (err: unknown) {
      setLoadError(err instanceof Error ? err.message : "Gagal memuat daftar pegawai dari server.");
    } finally {
      setLoadingDivisions(false);
    }
  };

  useEffect(() => {
    loadEmployees();
  }, []);

  // Auto-Reset all temporary identity state
  const handleResetAll = () => {
    setSelectedEmployee(null);
    setVerifiedPin(null);
    setGpsData(null);
    setSubmitting(false);
    setAttendanceResult(null);
  };

  const handlePinVerified = (pin: string) => {
    setVerifiedPin(pin);
  };

  // Step C & D: Camera Selfie Captured -> Submit Attendance
  const handlePhotoCaptured = async (blob: Blob) => {
    if (!selectedEmployee || !verifiedPin || !gpsData || submitting) return;

    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append("employee_id", selectedEmployee.id.toString());
      formData.append("pin", verifiedPin);
      formData.append("latitude", gpsData.lat.toString());
      formData.append("longitude", gpsData.lng.toString());
      formData.append("accuracy", gpsData.accuracy.toString());
      formData.append("selfie", blob, "selfie.jpg");

      const res = await submitAttendance(formData);
      setAttendanceResult(res);
    } catch (err: unknown) {
      setAttendanceResult({
        success: false,
        message: err instanceof Error ? err.message : "Terjadi kesalahan koneksi saat mengirim presensi.",
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col justify-between">
      {/* Header Real-Time Clock */}
      <Header />

      {/* Main Flow Content */}
      <main className="flex-1 max-w-md w-full mx-auto p-4 space-y-4">
        {/* Loading Initial Data */}
        {loadingDivisions && (
          <div className="bg-white rounded-3xl p-8 text-center border border-slate-200 shadow-sm space-y-3">
            <Loader2 className="w-8 h-8 text-emerald-600 animate-spin mx-auto" />
            <p className="text-sm font-semibold text-slate-600">Memuat daftar pegawai SPPG...</p>
          </div>
        )}

        {/* Load Error State */}
        {loadError && (
          <div className="bg-rose-50 border border-rose-200 rounded-3xl p-6 text-center space-y-3">
            <AlertTriangle className="w-8 h-8 text-rose-500 mx-auto" />
            <p className="text-xs font-semibold text-rose-700">{loadError}</p>
            <button
              onClick={loadEmployees}
              className="px-4 py-2 bg-rose-600 text-white rounded-xl text-xs font-bold shadow-sm"
            >
              Coba Lagi
            </button>
          </div>
        )}

        {/* Step A: Select Employee */}
        {!loadingDivisions && !loadError && !selectedEmployee && (
          <EmployeeSelector
            divisions={divisions}
            onSelectEmployee={(emp) => setSelectedEmployee(emp)}
          />
        )}

        {/* Step B: Input PIN */}
        {selectedEmployee && !verifiedPin && (
          <PinPad
            employee={selectedEmployee}
            onCancel={handleResetAll}
            onVerifySuccess={handlePinVerified}
            onVerifyError={() => {}}
          />
        )}

        {/* Step C & D: Camera Selfie & GPS */}
        {selectedEmployee && verifiedPin && !attendanceResult && (
          <div className="space-y-4">
            {/* GPS Status Indicator */}
            <GpsIndicator
              onLocationAcquired={(lat, lng, acc) => setGpsData({ lat, lng, accuracy: acc })}
              onError={() => {}}
            />

            {/* Live Camera Viewfinder */}
            {gpsData ? (
              <CameraViewfinder
                onCapture={handlePhotoCaptured}
                onCancel={handleResetAll}
              />
            ) : (
              <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-center text-xs text-amber-800 font-medium">
                Sistem sedang mengunci koordinat GPS Anda sebelum membuka kamera...
              </div>
            )}
          </div>
        )}

        {/* Submitting Loading Overlay */}
        {submitting && (
          <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div className="bg-white rounded-3xl p-6 text-center shadow-xl space-y-3">
              <Loader2 className="w-10 h-10 text-emerald-600 animate-spin mx-auto" />
              <p className="text-sm font-bold text-slate-800">Memvalidasi Presensi ke Server...</p>
              <p className="text-xs text-slate-500">Mohon tunggu sebentar</p>
            </div>
          </div>
        )}

        {/* Step F: Result Modal & Auto-Reset */}
        {attendanceResult && (
          <AttendanceResultModal
            result={attendanceResult}
            onReset={handleResetAll}
          />
        )}
      </main>

      {/* Footer */}
      <footer className="py-4 text-center text-[11px] text-slate-400 font-medium">
        &copy; {new Date().getFullYear()} Satuan Pelayanan Pemenuhan Gizi (SPPG) Pakiskembar 2
      </footer>
    </div>
  );
}
