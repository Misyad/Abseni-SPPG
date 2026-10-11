"use client";

import { useEffect, useState } from "react";
import { AttendanceResult } from "@/lib/api";
import { CheckCircle2, AlertTriangle, XCircle, Calendar, Clock, User, ArrowRight } from "lucide-react";

interface AttendanceResultModalProps {
  result: {
    success: boolean;
    message: string;
    error_code?: string;
    data?: AttendanceResult;
  };
  onReset: () => void;
}

export default function AttendanceResultModal({ result, onReset }: AttendanceResultModalProps) {
  const [countdown, setCountdown] = useState<number>(5);

  useEffect(() => {
    const timer = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          onReset();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [onReset]);

  const data = result.data;
  const isSuccess = result.success && data && data.status !== 'needs_review';
  const isFlagged = data?.status === 'needs_review' || data?.is_flagged;
  const isScheduleMissing = result.error_code === 'SCHEDULE_MISSING';

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 max-w-sm w-full space-y-6 text-center animate-in fade-in zoom-in-95 duration-200">
        {/* Status Icon */}
        <div className="mx-auto w-20 h-20 rounded-3xl flex items-center justify-center shadow-lg">
          {isSuccess && (
            <div className="w-full h-full bg-emerald-100 text-emerald-600 rounded-3xl flex items-center justify-center">
              <CheckCircle2 className="w-12 h-12" />
            </div>
          )}
          {isFlagged && (
            <div className="w-full h-full bg-amber-100 text-amber-600 rounded-3xl flex items-center justify-center">
              <AlertTriangle className="w-12 h-12" />
            </div>
          )}
          {(!result.success && !isScheduleMissing && !isFlagged) && (
            <div className="w-full h-full bg-rose-100 text-rose-600 rounded-3xl flex items-center justify-center">
              <XCircle className="w-12 h-12" />
            </div>
          )}
          {isScheduleMissing && (
            <div className="w-full h-full bg-rose-100 text-rose-600 rounded-3xl flex items-center justify-center">
              <AlertTriangle className="w-12 h-12" />
            </div>
          )}
        </div>

        {/* Status Title */}
        <div className="space-y-1">
          <h2 className="text-xl font-extrabold text-slate-800">
            {isSuccess && "Presensi Berhasil!"}
            {isFlagged && "Presensi Perlu Peninjauan"}
            {isScheduleMissing && "Jadwal Kerja Tidak Ditemukan"}
            {!result.success && !isScheduleMissing && !isFlagged && "Presensi Gagal"}
          </h2>
          <p className="text-xs text-slate-600 font-medium px-2 leading-relaxed">
            {result.message}
          </p>
        </div>

        {/* Result Detail Card */}
        {data && (
          <div className="bg-slate-50 rounded-2xl p-4 border border-slate-200 text-left space-y-2.5 text-xs">
            <div className="flex items-center justify-between border-b border-slate-200/80 pb-2">
              <span className="text-slate-500 font-medium flex items-center space-x-1">
                <User className="w-3.5 h-3.5" />
                <span>Nama Pegawai</span>
              </span>
              <span className="font-bold text-slate-800">{data.employee?.name || "Pegawai"}</span>
            </div>

            <div className="flex items-center justify-between border-b border-slate-200/80 pb-2">
              <span className="text-slate-500 font-medium flex items-center space-x-1">
                <Clock className="w-3.5 h-3.5" />
                <span>Tipe Aksi</span>
              </span>
              <span className="font-bold text-emerald-700 uppercase">
                {data.action === "check_in" ? "Masuk (Check-in)" : "Pulang (Check-out)"}
              </span>
            </div>

            <div className="flex items-center justify-between border-b border-slate-200/80 pb-2">
              <span className="text-slate-500 font-medium flex items-center space-x-1">
                <Calendar className="w-3.5 h-3.5" />
                <span>Tanggal Kerja (work_date)</span>
              </span>
              <span className="font-mono font-bold text-slate-800">{data.work_date}</span>
            </div>

            {data.shift && (
              <div className="flex items-center justify-between pt-0.5">
                <span className="text-slate-500 font-medium">Shift Kerja</span>
                <span className="font-semibold text-slate-700">{data.shift.name}</span>
              </div>
            )}
          </div>
        )}

        {/* Auto Reset Countdown & Selesai Button */}
        <div className="space-y-3 pt-2">
          <button
            onClick={onReset}
            className="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-2xl transition-all shadow-md active:scale-95 flex items-center justify-center space-x-2"
          >
            <span>Selesai ({countdown}s)</span>
            <ArrowRight className="w-4 h-4" />
          </button>
          <p className="text-[11px] text-slate-400 font-medium">
            Layar akan otomatis kembali ke menu awal untuk giliran pegawai berikutnya
          </p>
        </div>
      </div>
    </div>
  );
}
