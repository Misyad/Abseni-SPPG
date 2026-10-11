export const API_BASE_URL = (process.env.NEXT_PUBLIC_API_BASE_URL || '/api/v1').replace(/\/$/, '');

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
    this.name = 'ApiError';
  }
}

export function buildApiUrl(path: string): string {
  return `${API_BASE_URL}/${path.replace(/^\//, '')}`;
}

export interface Division {
  id: number;
  code: string;
  name: string;
  description?: string;
  employees?: EmployeeSimple[];
}

export interface EmployeeSimple {
  id: number;
  division_id: number;
  employee_code: string;
  name: string;
}

export interface Shift {
  id: number;
  code: string;
  name: string;
  start_time: string;
  end_time: string;
  check_in_window_start_minutes: number;
  check_in_window_end_minutes: number;
  late_tolerance_minutes: number;
  check_out_window_start_minutes: number;
  check_out_window_end_minutes: number;
  is_cross_day: boolean;
}

export interface VerifyPinResponse {
  success: boolean;
  message: string;
  data?: {
    employee_id: number;
    employee_name: string;
    expected_action: 'check_in' | 'check_out';
    work_date: string;
    shift: Shift;
  };
}

export type AttendanceStatus = 'on_time' | 'late' | 'early_leave' | 'absent' | 'needs_review' | 'corrected';

export interface AdminUser {
  id: number;
  name: string;
  email?: string;
  role: string;
}

export interface AdminMetrics {
  date?: string;
  total_scheduled: number;
  on_time: number;
  late: number;
  needs_review: number;
  attendance_attempts: number;
}

export interface AdminAttendance {
  id: number;
  work_date: string;
  status: AttendanceStatus;
  is_flagged: boolean;
  flag_reason?: string | null;
  check_in_at?: string | null;
  check_out_at?: string | null;
  check_in_lat?: number | null;
  check_in_lng?: number | null;
  has_check_in_selfie: boolean;
  has_check_out_selfie: boolean;
  client_ip?: string | null;
  employee?: EmployeeSimple;
  shift?: Shift;
}

interface PaginatedResponse<T> {
  data: T[];
}

export interface AttendanceSubmissionResponse {
  success: boolean;
  message: string;
  data?: AttendanceResult;
  error_code?: string;
}

export interface AttendanceResult {
  id: number;
  employee_id: number;
  action: 'check_in' | 'check_out';
  work_date: string;
  status: AttendanceStatus;
  check_in_at?: string;
  check_out_at?: string;
  late_minutes?: number;
  early_leave_minutes?: number;
  is_flagged?: boolean;
  flag_reason?: string;
  employee?: {
    name: string;
    employee_code: string;
  };
  shift?: Shift;
}

export async function fetchPublicEmployees(): Promise<Division[]> {
  const res = await fetch(buildApiUrl('public/employees'), {
    cache: 'no-store',
  });
  if (!res.ok) {
    throw new Error('Gagal mengambil daftar pegawai');
  }
  const json = await res.json();
  return json.data || [];
}

export async function verifyEmployeePin(employeeId: number, pin: string): Promise<VerifyPinResponse> {
  const res = await fetch(buildApiUrl('public/employees/verify-pin'), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ employee_id: employeeId, pin }),
  });

  const json = await res.json();
  if (!res.ok) {
    return {
      success: false,
      message: json.message || 'PIN salah atau gagal diverifikasi',
    };
  }

  return json;
}

export async function submitAttendance(formData: FormData): Promise<AttendanceSubmissionResponse> {
  const res = await fetch(buildApiUrl('public/attendances/submit'), {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
    },
    body: formData,
  });

  const json = await res.json();
  if (!res.ok && !json.data) {
    return {
      success: false,
      message: json.message || 'Gagal mengirimkan presensi',
      error_code: json.error_code,
    };
  }

  return json;
}

export async function adminLogin(email: string, password: string): Promise<{ token: string; admin: AdminUser }> {
  const res = await fetch(buildApiUrl('admin/auth/login'), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });

  const json = await res.json();
  if (!res.ok) {
    throw new Error(json.message || 'Login gagal');
  }

  return json.data;
}

export async function fetchAdminDashboard(token: string, date?: string): Promise<{ date: string; metrics: AdminMetrics }> {
  const url = date ? buildApiUrl(`admin/dashboard?date=${date}`) : buildApiUrl('admin/dashboard');
  const res = await fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!res.ok) {
    throw new ApiError('Gagal mengambil data dashboard', res.status);
  }

  const json = await res.json();
  return json.data;
}

export async function fetchAdminAttendances(token: string, filters: { date?: string; status?: string; flagged?: boolean } = {}): Promise<PaginatedResponse<AdminAttendance>> {
  const params = new URLSearchParams();
  if (filters.date) params.append('work_date', filters.date);
  if (filters.status) params.append('status', filters.status);
  if (filters.flagged) params.append('is_flagged', 'true');

  const res = await fetch(buildApiUrl(`admin/attendances?${params.toString()}`), {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!res.ok) {
    throw new ApiError('Gagal mengambil data absensi admin', res.status);
  }

  const json = await res.json();
  return json.data;
}

export async function correctAttendance(token: string, id: number, status: string, reason: string): Promise<AdminAttendance> {
  const res = await fetch(buildApiUrl(`admin/attendances/${id}/correct`), {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ status, reason }),
  });

  const json = await res.json();
  if (!res.ok) {
    throw new ApiError(json.message || 'Gagal menyimpan koreksi', res.status);
  }

  return json.data;
}

export async function fetchAdminSelfie(token: string, id: number, type: 'check_in' | 'check_out'): Promise<Blob> {
  const res = await fetch(buildApiUrl(`admin/media/selfie/${id}/${type}`), {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'image/*',
    },
    cache: 'no-store',
  });

  if (!res.ok) {
    throw new ApiError('Gagal mengambil selfie privat', res.status);
  }

  return res.blob();
}

export async function adminLogout(token: string): Promise<void> {
  const res = await fetch(buildApiUrl('admin/auth/logout'), {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!res.ok && res.status !== 401) {
    throw new Error('Gagal mencabut sesi admin');
  }
}
