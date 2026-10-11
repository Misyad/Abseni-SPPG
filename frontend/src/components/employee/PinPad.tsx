"use client";

import { useState } from "react";
import { EmployeeSimple, verifyEmployeePin } from "@/lib/api";
import { ArrowLeft, Delete, KeyRound, Loader2, Lock } from "lucide-react";

interface PinPadProps {
  employee: EmployeeSimple;
  onCancel: () => void;
  onVerifySuccess: (pin: string) => void;
  onVerifyError: (errorMessage: string) => void;
}

export default function PinPad({ employee, onCancel, onVerifySuccess, onVerifyError }: PinPadProps) {
  const [pin, setPin] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleKeyPress = (num: string) => {
    if (loading) return;
    if (pin.length < 6) {
      setPin((prev) => prev + num);
      setErrorMessage(null);
    }
  };

  const handleBackspace = () => {
    if (loading) return;
    setPin((prev) => prev.slice(0, -1));
    setErrorMessage(null);
  };

  const handleSubmit = async () => {
    if (pin.length !== 6 || loading) return;

    setLoading(true);
    setErrorMessage(null);

    try {
      const response = await verifyEmployeePin(employee.id, pin);

      if (!response.success) {
        const errorMsg = response.message || "PIN salah atau gagal diverifikasi.";
        setErrorMessage(errorMsg);
        onVerifyError(errorMsg);
        setPin("");
      } else {
        onVerifySuccess(pin);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Terjadi kesalahan jaringan saat verifikasi PIN.";
      setErrorMessage(msg);
      onVerifyError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white rounded-3xl p-6 shadow-md border border-slate-200 space-y-6 max-w-sm mx-auto">
      {/* Top Bar */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-3">
        <button
          onClick={onCancel}
          disabled={loading}
          className="p-2 rounded-xl text-slate-500 hover:bg-slate-100 transition-colors flex items-center space-x-1 text-xs font-semibold"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Kembali</span>
        </button>
        <span className="text-xs font-mono font-bold text-slate-400">
          {employee.employee_code}
        </span>
      </div>

      {/* Identitas Pegawai */}
      <div className="text-center space-y-1">
        <div className="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 font-bold text-lg mx-auto flex items-center justify-center">
          {employee.name.charAt(0).toUpperCase()}
        </div>
        <h2 className="text-base font-bold text-slate-800">{employee.name}</h2>
        <p className="text-xs text-slate-500">Masukkan 6 digit PIN pribadi Anda</p>
      </div>

      {/* Indikator Dots PIN */}
      <div className="flex justify-center space-x-2 py-2">
        {Array.from({ length: 6 }, (_, idx) => idx).map((idx) => (
          <div
            key={idx}
            className={`w-4 h-4 rounded-full border-2 transition-all ${
              pin.length > idx
                ? "bg-emerald-600 border-emerald-600 scale-110"
                : "border-slate-300 bg-slate-50"
            }`}
          />
        ))}
      </div>

      {/* Error Message */}
      {errorMessage && (
        <div className="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-3 text-xs text-center font-medium flex items-center justify-center space-x-1.5 animate-shake">
          <Lock className="w-4 h-4 text-rose-500 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      {/* Virtual Keypad */}
      <div className="grid grid-cols-3 gap-3">
        {["1", "2", "3", "4", "5", "6", "7", "8", "9"].map((num) => (
          <button
            key={num}
            onClick={() => handleKeyPress(num)}
            disabled={loading}
            className="h-14 rounded-2xl bg-slate-100 hover:bg-emerald-50 active:bg-emerald-100 text-slate-800 font-bold text-xl flex items-center justify-center transition-colors active:scale-95 disabled:opacity-50"
          >
            {num}
          </button>
        ))}

        <button
          onClick={handleBackspace}
          disabled={loading || pin.length === 0}
          className="h-14 rounded-2xl bg-slate-100 hover:bg-rose-50 text-slate-600 flex items-center justify-center transition-colors active:scale-95 disabled:opacity-40"
        >
          <Delete className="w-5 h-5 text-slate-600" />
        </button>

        <button
          onClick={() => handleKeyPress("0")}
          disabled={loading}
          className="h-14 rounded-2xl bg-slate-100 hover:bg-emerald-50 text-slate-800 font-bold text-xl flex items-center justify-center transition-colors active:scale-95 disabled:opacity-50"
        >
          0
        </button>

        <button
          onClick={handleSubmit}
          disabled={loading || pin.length !== 6}
          className="h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-sm flex items-center justify-center transition-all active:scale-95 disabled:opacity-40 shadow-sm"
        >
          {loading ? (
            <Loader2 className="w-5 h-5 animate-spin" />
          ) : (
            <KeyRound className="w-5 h-5" />
          )}
        </button>
      </div>
    </div>
  );
}
