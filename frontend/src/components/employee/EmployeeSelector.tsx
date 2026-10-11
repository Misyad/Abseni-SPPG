"use client";

import { useState } from "react";
import { Division, EmployeeSimple } from "@/lib/api";
import { Search, UserCheck, Users } from "lucide-react";

interface EmployeeSelectorProps {
  divisions: Division[];
  onSelectEmployee: (employee: EmployeeSimple) => void;
}

export default function EmployeeSelector({ divisions, onSelectEmployee }: EmployeeSelectorProps) {
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedDivisionId, setSelectedDivisionId] = useState<number | null>(null);

  // Flatten employees with division name attached
  const allEmployees: (EmployeeSimple & { divisionName: string })[] = divisions.flatMap((div) =>
    (div.employees || []).map((emp) => ({
      ...emp,
      divisionName: div.name,
    }))
  );

  const filteredEmployees = allEmployees.filter((emp) => {
    const matchesSearch =
      emp.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      emp.employee_code.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesDivision = selectedDivisionId ? emp.division_id === selectedDivisionId : true;

    return matchesSearch && matchesDivision;
  });

  return (
    <div className="space-y-4">
      {/* Header Info */}
      <div className="bg-white rounded-2xl p-4 shadow-sm border border-slate-200 text-center space-y-1">
        <h2 className="text-lg font-bold text-slate-800">Pilih Identitas Pegawai</h2>
        <p className="text-xs text-slate-500">
          Silakan pilih nama Anda dari daftar di bawah untuk mulai presensi
        </p>
      </div>

      {/* Search Bar */}
      <div className="relative">
        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="Cari nama pegawai atau NIP..."
          className="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm"
        />
      </div>

      {/* Division Tabs */}
      <div className="flex items-center space-x-2 overflow-x-auto pb-1 scrollbar-none">
        <button
          onClick={() => setSelectedDivisionId(null)}
          className={`px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors ${
            selectedDivisionId === null
              ? "bg-emerald-600 text-white shadow-sm"
              : "bg-white text-slate-600 border border-slate-200 hover:bg-slate-50"
          }`}
        >
          Semua Divisi
        </button>
        {divisions.map((div) => (
          <button
            key={div.id}
            onClick={() => setSelectedDivisionId(div.id)}
            className={`px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors ${
              selectedDivisionId === div.id
                ? "bg-emerald-600 text-white shadow-sm"
                : "bg-white text-slate-600 border border-slate-200 hover:bg-slate-50"
            }`}
          >
            {div.name}
          </button>
        ))}
      </div>

      {/* Employee Grid List */}
      <div className="space-y-2 max-h-[60vh] overflow-y-auto pr-1">
        {filteredEmployees.length === 0 ? (
          <div className="bg-white rounded-2xl p-8 text-center border border-slate-200 space-y-2">
            <Users className="w-8 h-8 text-slate-300 mx-auto" />
            <p className="text-sm font-medium text-slate-500">
              Tidak ada pegawai aktif ditemukan
            </p>
          </div>
        ) : (
          filteredEmployees.map((emp) => (
            <button
              key={emp.id}
              onClick={() => onSelectEmployee(emp)}
              className="w-full bg-white hover:bg-emerald-50/60 active:scale-[0.99] transition-all duration-150 rounded-2xl p-3.5 border border-slate-200 hover:border-emerald-500 shadow-sm flex items-center justify-between text-left group"
            >
              <div className="flex items-center space-x-3">
                <div className="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 font-bold text-sm flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                  {emp.name.charAt(0).toUpperCase()}
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-800 group-hover:text-emerald-900">
                    {emp.name}
                  </h3>
                  <div className="flex items-center space-x-2 text-xs text-slate-500 mt-0.5">
                    <span className="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">
                      {emp.employee_code}
                    </span>
                    <span>•</span>
                    <span>{emp.divisionName}</span>
                  </div>
                </div>
              </div>
              <UserCheck className="w-5 h-5 text-slate-300 group-hover:text-emerald-600 transition-colors" />
            </button>
          ))
        )}
      </div>
    </div>
  );
}
