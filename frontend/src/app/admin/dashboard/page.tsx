"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import dynamic from "next/dynamic";
import {
  AdminAttendance,
  AdminMetrics,
  AdminUser,
  ApiError,
  adminLogout,
  correctAttendance,
  fetchAdminAttendances,
  fetchAdminDashboard,
  fetchAdminSelfie,
} from "@/lib/api";
import { ShieldCheck, LogOut, AlertTriangle, Eye, Edit3, Loader2 } from "lucide-react";

// Dynamically import Leaflet Map (client-side only)
const LeafletMap = dynamic(() => import("@/components/admin/LeafletMap"), { ssr: false });

export default function AdminDashboardPage() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [adminUser, setAdminUser] = useState<AdminUser | null>(null);
  const canViewSelfie = adminUser?.role === "supervisor" || adminUser?.role === "superadmin";

  const [metrics, setMetrics] = useState<AdminMetrics | null>(null);
  const [attendances, setAttendances] = useState<AdminAttendance[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [activeTab, setActiveTab] = useState<"dashboard" | "review" | "all">("dashboard");

  // Detail Modal State
  const [selectedAttendance, setSelectedAttendance] = useState<AdminAttendance | null>(null);
  const [selfieUrl, setSelfieUrl] = useState<string | null>(null);
  const [selfieError, setSelfieError] = useState<string | null>(null);
  const [correctingAttendance, setCorrectingAttendance] = useState<AdminAttendance | null>(null);
  const [correctionStatus, setCorrectionStatus] = useState<string>("on_time");
  const [correctionReason, setCorrectionReason] = useState<string>("");
  const [savingCorrection, setSavingCorrection] = useState<boolean>(false);

  const clearAdminSession = useCallback(() => {
    sessionStorage.removeItem("admin_token");
    sessionStorage.removeItem("admin_user");
    router.push("/admin/login");
  }, [router]);

  const loadData = useCallback(async (authToken: string) => {
    setLoading(true);
    try {
      const dashData = await fetchAdminDashboard(authToken);
      setMetrics(dashData.metrics);

      const listData = await fetchAdminAttendances(authToken);
      setAttendances(listData.data || []);
    } catch (err: unknown) {
      if (err instanceof ApiError && err.status === 401) {
        clearAdminSession();
      }
    } finally {
      setLoading(false);
    }
  }, [clearAdminSession]);

  useEffect(() => {
    const savedToken = sessionStorage.getItem("admin_token");
    const savedUser = sessionStorage.getItem("admin_user");

    if (!savedToken) {
      router.push("/admin/login");
      return;
    }

    setToken(savedToken);
    if (savedUser) setAdminUser(JSON.parse(savedUser) as AdminUser);

    void loadData(savedToken);
  }, [loadData, router]);

  useEffect(() => {
    if (!token || !canViewSelfie || !selectedAttendance?.has_check_in_selfie) {
      setSelfieUrl(null);
      setSelfieError(null);
      return;
    }

    let objectUrl: string | null = null;
    let cancelled = false;
    setSelfieError(null);

    void fetchAdminSelfie(token, selectedAttendance.id, "check_in")
      .then((blob) => {
        if (cancelled) return;
        objectUrl = URL.createObjectURL(blob);
        setSelfieUrl(objectUrl);
      })
      .catch((error: unknown) => {
        if (error instanceof ApiError && error.status === 401) {
          clearAdminSession();
          return;
        }
        if (!cancelled) setSelfieError(error instanceof Error ? error.message : "Gagal memuat selfie privat");
      });

    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      setSelfieUrl(null);
    };
  }, [canViewSelfie, clearAdminSession, selectedAttendance, token]);

  const handleLogout = async () => {
    if (token) {
      try {
        await adminLogout(token);
      } catch {
        // Token tetap dibersihkan di client; kegagalan revoke ditangani oleh expiry server.
      }
    }
    sessionStorage.removeItem("admin_token");
    sessionStorage.removeItem("admin_user");
    router.push("/admin/login");
  };

  const handleSaveCorrection = async () => {
    if (!token || !correctingAttendance || !correctionReason.trim()) return;

    setSavingCorrection(true);
    try {
      await correctAttendance(token, correctingAttendance.id, correctionStatus, correctionReason);
      setCorrectingAttendance(null);
      setCorrectionReason("");
      await loadData(token);
    } catch (err: unknown) {
      alert("Gagal menyimpan koreksi: " + (err instanceof Error ? err.message : "Kesalahan tidak diketahui"));
    } finally {
      setSavingCorrection(false);
    }
  };

  const reviewQueue = attendances.filter((att) => att.is_flagged || att.status === "needs_review");
  const canCorrect = adminUser?.role === "supervisor" || adminUser?.role === "superadmin";

  return (
    <div className="min-h-screen bg-slate-100 flex flex-col">
      {/* Admin Navbar */}
      <header className="bg-slate-900 text-white px-6 py-4 flex items-center justify-between shadow-md">
        <div className="flex items-center space-x-3">
          <ShieldCheck className="w-6 h-6 text-emerald-400" />
          <div>
            <h1 className="text-base font-bold">Portal Admin SPPG Pakiskembar 2</h1>
            <p className="text-xs text-slate-400">Monitoring & Manajemen Kehadiran</p>
          </div>
        </div>

        <div className="flex items-center space-x-4">
          <div className="text-right hidden sm:block">
            <p className="text-xs font-bold">{adminUser?.name || "Admin"}</p>
            <p className="text-[10px] text-emerald-400 uppercase font-semibold">{adminUser?.role || "superadmin"}</p>
          </div>
          <button
            onClick={handleLogout}
            className="p-2 rounded-xl bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white transition-colors"
            title="Logout"
          >
            <LogOut className="w-5 h-5" />
          </button>
        </div>
      </header>

      {/* Main Container */}
      <main className="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 space-y-6">
        {/* Navigation Tabs */}
        <div className="flex items-center space-x-2 border-b border-slate-200 pb-2 overflow-x-auto">
          <button
            onClick={() => setActiveTab("dashboard")}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-colors ${
              activeTab === "dashboard" ? "bg-emerald-600 text-white" : "bg-white text-slate-600 hover:bg-slate-200"
            }`}
          >
            Dashboard Ringkasan
          </button>
          <button
            onClick={() => setActiveTab("review")}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-colors relative ${
              activeTab === "review" ? "bg-amber-600 text-white" : "bg-white text-slate-600 hover:bg-slate-200"
            }`}
          >
            <span>Review Center (Anomali)</span>
            {reviewQueue.length > 0 && (
              <span className="ml-2 bg-rose-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">
                {reviewQueue.length}
              </span>
            )}
          </button>
          <button
            onClick={() => setActiveTab("all")}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-colors ${
              activeTab === "all" ? "bg-slate-800 text-white" : "bg-white text-slate-600 hover:bg-slate-200"
            }`}
          >
            Semua Data Absensi
          </button>
        </div>

        {/* Loading State */}
        {loading && (
          <div className="bg-white rounded-3xl p-12 text-center space-y-3 shadow-sm border border-slate-200">
            <Loader2 className="w-8 h-8 text-emerald-600 animate-spin mx-auto" />
            <p className="text-sm font-semibold text-slate-600">Memuat data monitoring admin...</p>
          </div>
        )}

        {!loading && activeTab === "dashboard" && (
          <div className="space-y-6">
            {/* Metrics Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div className="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-1">
                <span className="text-xs text-slate-500 font-semibold">Total Terjadwal</span>
                <p className="text-2xl font-extrabold text-slate-800">{metrics?.total_scheduled || 0}</p>
              </div>
              <div className="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm space-y-1">
                <span className="text-xs text-emerald-600 font-semibold">Hadir Tepat Waktu</span>
                <p className="text-2xl font-extrabold text-emerald-600">{metrics?.on_time || 0}</p>
              </div>
              <div className="bg-white rounded-2xl p-4 border border-amber-200 shadow-sm space-y-1">
                <span className="text-xs text-amber-600 font-semibold">Terlambat</span>
                <p className="text-2xl font-extrabold text-amber-600">{metrics?.late || 0}</p>
              </div>
              <div className="bg-white rounded-2xl p-4 border border-rose-200 shadow-sm space-y-1">
                <span className="text-xs text-rose-600 font-semibold">Perlu Review / Anomali</span>
                <p className="text-2xl font-extrabold text-rose-600">
                  {(metrics?.needs_review || 0) + (metrics?.attendance_attempts || 0)}
                </p>
              </div>
            </div>

            {/* Attendance Stream Table */}
            <div className="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-base font-bold text-slate-800">Stream Presensi Real-Time Hari Ini</h3>
                <span className="text-xs text-slate-500 font-medium">Tanggal: {metrics?.date}</span>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase">
                    <tr>
                      <th className="p-3">Pegawai</th>
                      <th className="p-3">Shift</th>
                      <th className="p-3">Aksi & Waktu</th>
                      <th className="p-3">Status</th>
                      <th className="p-3 text-right">Aksi Admin</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 font-medium">
                    {attendances.map((att) => (
                      <tr key={att.id} className="hover:bg-slate-50/80">
                        <td className="p-3">
                          <p className="font-bold text-slate-800">{att.employee?.name}</p>
                          <p className="text-[10px] text-slate-400">{att.employee?.employee_code}</p>
                        </td>
                        <td className="p-3 text-slate-600">{att.shift?.name || "-"}</td>
                        <td className="p-3">
                          <p className="font-semibold text-emerald-700">
                            {att.check_out_at ? "Check-out" : "Check-in"}
                          </p>
                          <p className="text-[10px] text-slate-500 font-mono">
                            {att.check_out_at || att.check_in_at || "-"}
                          </p>
                        </td>
                        <td className="p-3">
                          <span
                            className={`px-2.5 py-1 rounded-full text-[10px] font-bold ${
                              att.status === "on_time"
                                ? "bg-emerald-100 text-emerald-800"
                                : att.status === "late"
                                ? "bg-amber-100 text-amber-800"
                                : "bg-purple-100 text-purple-800"
                            }`}
                          >
                            {att.status}
                          </span>
                        </td>
                        <td className="p-3 text-right space-x-1">
                          <button
                            onClick={() => setSelectedAttendance(att)}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"
                            title="Detail"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          {canCorrect && (
                            <button
                              onClick={() => {
                                setCorrectingAttendance(att);
                                setCorrectionStatus(att.status);
                              }}
                              className="p-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-800"
                              title="Koreksi"
                            >
                              <Edit3 className="w-4 h-4" />
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {!loading && activeTab === "review" && (
          <div className="bg-white rounded-3xl p-6 border border-amber-200 shadow-sm space-y-4">
            <h3 className="text-base font-bold text-amber-900 flex items-center space-x-2">
              <AlertTriangle className="w-5 h-5 text-amber-600" />
              <span>Review Center (Anomali Presensi & Jadwal)</span>
            </h3>
            <p className="text-xs text-slate-500">
              Daftar presensi yang membutuhkan konfirmasi manual atau penetapan jadwal oleh Admin.
            </p>

            <div className="space-y-3">
              {reviewQueue.length === 0 ? (
                <div className="p-8 text-center text-xs text-slate-400 font-medium">
                  Tidak ada data anomali yang membutuhkan review saat ini.
                </div>
              ) : (
                reviewQueue.map((att) => (
                  <div key={att.id} className="p-4 rounded-2xl border border-amber-200 bg-amber-50/50 flex items-center justify-between">
                    <div>
                      <h4 className="text-sm font-bold text-slate-800">{att.employee?.name}</h4>
                      <p className="text-xs text-amber-800 font-medium">{att.flag_reason || "Status: " + att.status}</p>
                      <p className="text-[10px] text-slate-400">Tanggal Kerja: {att.work_date}</p>
                    </div>

                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => setSelectedAttendance(att)}
                        className="px-3 py-1.5 rounded-xl bg-slate-800 text-white text-xs font-bold"
                      >
                        Periksa Detail
                      </button>
                      {canCorrect && (
                        <button
                          onClick={() => {
                            setCorrectingAttendance(att);
                            setCorrectionStatus("on_time");
                          }}
                          className="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold"
                        >
                          Koreksi Status
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        )}
      </main>

      {/* Detail Modal (Leaflet Map & Private Selfie Proxy) */}
      {selectedAttendance && (
        <div className="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl p-6 max-w-lg w-full space-y-4 my-8">
            <div className="flex items-center justify-between border-b pb-3">
              <h3 className="text-base font-bold text-slate-800">Detail Presensi #{selectedAttendance.id}</h3>
              <button onClick={() => setSelectedAttendance(null)} className="text-slate-400 hover:text-slate-600 font-bold text-lg">
                &times;
              </button>
            </div>

            <div className="space-y-2 text-xs">
              <p><strong>Pegawai:</strong> {selectedAttendance.employee?.name} ({selectedAttendance.employee?.employee_code})</p>
              <p><strong>Tanggal Kerja:</strong> {selectedAttendance.work_date}</p>
              <p><strong>Status:</strong> {selectedAttendance.status}</p>
              <p><strong>IP Client:</strong> {selectedAttendance.client_ip}</p>
            </div>

            {/* Visualisasi Peta Leaflet */}
            {typeof selectedAttendance.check_in_lat === "number" && typeof selectedAttendance.check_in_lng === "number" && (
              <div className="space-y-1">
                <span className="text-xs font-bold text-slate-700">Visualisasi Peta Lokasi (OpenStreetMap)</span>
                <LeafletMap
                  lat={selectedAttendance.check_in_lat}
                  lng={selectedAttendance.check_in_lng}
                />
              </div>
            )}

            {/* Proxy Photo Selfie Private */}
            {canViewSelfie && selectedAttendance.has_check_in_selfie && (
              <div className="space-y-1">
                <span className="text-xs font-bold text-slate-700">Foto Selfie (Private Proxy)</span>
                {selfieUrl ? (
                  <img
                    src={selfieUrl}
                    alt="Selfie Check-in"
                    className="w-full h-48 object-cover rounded-2xl border border-slate-200"
                  />
                ) : (
                  <div className="w-full h-48 rounded-2xl border border-slate-200 bg-slate-50 flex items-center justify-center text-xs text-slate-500">
                    {selfieError || "Memuat selfie privat..."}
                  </div>
                )}
              </div>
            )}

            <button
              onClick={() => setSelectedAttendance(null)}
              className="w-full py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold"
            >
              Tutup
            </button>
          </div>
        </div>
      )}

      {/* Manual Correction Modal */}
      {correctingAttendance && (
        <div className="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 max-w-sm w-full space-y-4">
            <h3 className="text-base font-bold text-slate-800">Koreksi Absensi Manual</h3>
            <p className="text-xs text-slate-500">Pegawai: {correctingAttendance.employee?.name}</p>

            <div className="space-y-3 text-xs">
              <div>
                <label className="font-bold text-slate-700">Status Kehadiran Baru</label>
                <select
                  value={correctionStatus}
                  onChange={(e) => setCorrectionStatus(e.target.value)}
                  className="w-full p-2.5 mt-1 border rounded-xl bg-slate-50 text-slate-800 font-semibold"
                >
                  <option value="on_time">On Time (Hadir Tepat Waktu)</option>
                  <option value="late">Late (Terlambat)</option>
                  <option value="early_leave">Early Leave (Pulang Awal)</option>
                  <option value="absent">Absent (Alpha)</option>
                  <option value="corrected">Corrected (Dikoreksi Admin)</option>
                </select>
              </div>

              <div>
                <label className="font-bold text-slate-700">Alasan Koreksi (Wajib untuk Audit Log)</label>
                <textarea
                  required
                  rows={3}
                  value={correctionReason}
                  onChange={(e) => setCorrectionReason(e.target.value)}
                  placeholder="Tuliskan alasan koreksi..."
                  className="w-full p-2.5 mt-1 border rounded-xl bg-slate-50 text-slate-800"
                />
              </div>
            </div>

            <div className="flex items-center space-x-2 pt-2">
              <button
                onClick={() => setCorrectingAttendance(null)}
                className="flex-1 py-2.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold"
              >
                Batal
              </button>
              <button
                onClick={handleSaveCorrection}
                disabled={savingCorrection || !correctionReason.trim()}
                className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold disabled:opacity-40"
              >
                {savingCorrection ? <Loader2 className="w-4 h-4 animate-spin mx-auto" /> : "Simpan Koreksi"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
