"use client";

import { useEffect, useState } from "react";
import { Clock, ShieldCheck } from "lucide-react";

export default function Header() {
  const [timeStr, setTimeStr] = useState<string>("");
  const [dateStr, setDateStr] = useState<string>("");

  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      // Format Jam WIB Asia/Jakarta
      const formatterTime = new Intl.DateTimeFormat("id-ID", {
        timeZone: "Asia/Jakarta",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false,
      });
      const formatterDate = new Intl.DateTimeFormat("id-ID", {
        timeZone: "Asia/Jakarta",
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });

      setTimeStr(formatterTime.format(now));
      setDateStr(formatterDate.format(now));
    };

    updateTime();
    const interval = setInterval(updateTime, 1000);
    return () => clearInterval(interval);
  }, []);

  return (
    <header className="bg-emerald-700 text-white shadow-md px-4 py-4 sticky top-0 z-30">
      <div className="max-w-md mx-auto flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <div className="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-md flex items-center justify-center border border-white/20">
            <ShieldCheck className="w-6 h-6 text-emerald-200" />
          </div>
          <div>
            <h1 className="text-base font-bold leading-tight">Absensi SPPG</h1>
            <p className="text-xs text-emerald-200 font-medium">Pakiskembar 2</p>
          </div>
        </div>

        <div className="text-right">
          <div className="flex items-center justify-end space-x-1.5 text-white">
            <Clock className="w-4 h-4 text-emerald-300 animate-pulse" />
            <span className="text-lg font-mono font-bold tracking-tight">
              {timeStr || "--:--:--"}
            </span>
          </div>
          <p className="text-[11px] text-emerald-200">{dateStr || "Memuat tanggal..."}</p>
        </div>
      </div>
    </header>
  );
}
