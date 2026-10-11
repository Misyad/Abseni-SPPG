# API Specification

## 1. Konvensi

- Base URL: `/api/v1`.
- Waktu bisnis: `Asia/Jakarta`.
- JSON untuk request biasa; `multipart/form-data` untuk submit selfie.
- Validasi status, waktu, `work_date`, geofence, serta aksi check-in/check-out dilakukan backend.
- Error umum:

```json
{
  "success": false,
  "message": "Pesan aman",
  "error_code": "ERROR_CODE"
}
```

Laravel dapat mengembalikan `422` dengan objek `errors` untuk validation error.

## 2. Endpoint Publik

### `GET /public/employees`

Daftar divisi aktif beserta pegawai aktif. Field pegawai publik: `id`, `division_id`, `employee_code`, `name`.

### `POST /public/employees/verify-pin`

Pre-check sebelum GPS/kamera. Limiter: maksimal lima request per 15 menit untuk kombinasi IP dan `employee_id`; lock pegawai 15 menit setelah lima PIN gagal.

Request:

```json
{
  "employee_id": 1,
  "pin": "123456"
}
```

`pin` wajib string numerik tepat 6 digit yang dibuat/reset melalui endpoint admin. Respons PIN salah sengaja generik.

Respons `200`:

```json
{
  "success": true,
  "message": "PIN valid",
  "data": {
    "employee_id": 1,
    "employee_name": "Ahmad Fauzi",
    "expected_action": "check_in",
    "work_date": "2026-10-10",
    "shift": {
      "id": 3,
      "name": "Shift Malam",
      "start_time": "19:00:00",
      "end_time": "03:00:00",
      "check_in_window_start_minutes": 60,
      "check_in_window_end_minutes": 240,
      "late_tolerance_minutes": 15,
      "check_out_window_start_minutes": 30,
      "check_out_window_end_minutes": 240,
      "is_cross_day": true
    }
  }
}
```

Error: `INVALID_PIN` (`422`), `PIN_LOCKED`/throttle (`429`), `SCHEDULE_MISSING`, `SCHEDULE_AMBIGUOUS`, `OUTSIDE_ATTENDANCE_WINDOW` (`422`), `ALREADY_COMPLETED` (`409`). Penolakan jadwal/window dicatat sebagai `attendance_attempts`, bukan attendance.

### `POST /public/attendances/submit`

Limiter: sepuluh request per 15 menit untuk kombinasi IP dan `employee_id`.

| Field | Validasi |
|---|---|
| `employee_id` | integer wajib |
| `pin` | string numerik tepat 6 digit |
| `latitude` | -90 sampai 90 |
| `longitude` | -180 sampai 180 |
| `accuracy` | 0 sampai 10.000 meter; dicatat untuk audit |
| `selfie` | JPEG/PNG/WebP, maksimal 5 MB |

Respons sukses `201` berisi `action` dan representasi attendance aktual:

```json
{
  "success": true,
  "message": "Presensi Masuk (Check-in) berhasil dicatat.",
  "data": {
    "action": "check_in",
    "id": 105,
    "employee_id": 1,
    "location_id": 1,
    "shift_id": 3,
    "work_date": "2026-10-10",
    "status": "on_time",
    "late_minutes": 0,
    "is_flagged": false,
    "employee": {},
    "shift": {},
    "location": {}
  }
}
```

Error bisnis: `INVALID_PIN`, `OUT_OF_GEOFENCE`, `SCHEDULE_MISSING`, `SCHEDULE_AMBIGUOUS`, `OUTSIDE_ATTENDANCE_WINDOW` (`422`); `PIN_LOCKED` (`429`); `ALREADY_COMPLETED`, `SESSION_CONFLICT` (`409`). Error internal tidak membocorkan exception (`500 SERVER_ERROR`).

## 3. Autentikasi Admin

### `POST /admin/auth/login`

Body `{ "email": "...", "password": "..." }`. Limiter lima request/15 menit per IP dan hash email ternormalisasi. Hanya admin aktif. Respons sukses berisi token Sanctum dan profil admin.

### `GET /admin/auth/me`

Memerlukan Bearer token. Mengembalikan admin saat ini.

### `POST /admin/auth/logout`

Memerlukan Bearer token. Mencabut token aktif.

## 4. Matriks RBAC

| Role | Izin |
|---|---|
| `operator` | dashboard dan daftar/detail attendance |
| `supervisor` | izin operator + selfie privat + koreksi/resolve attendance + baca/tulis jadwal |
| `superadmin` | seluruh izin + divisi, pegawai/PIN, lokasi, shift, audit log |

Admin tidak aktif ditolak `403`, walaupun token masih ada. Proteksi dilakukan middleware backend; penyembunyian tombol frontend hanya UX.

## 5. Endpoint Admin

Semua rute memerlukan `Authorization: Bearer <token>`.

### Monitoring — operator+

- `GET /admin/dashboard?date=YYYY-MM-DD`
  - `metrics`: `total_scheduled`, `on_time`, `late`, `needs_review`, `attendance_attempts`.
  - `attendance_attempts` adalah jumlah attempt jadwal hilang/ambigu atau di luar window pada tanggal tersebut.
  - `recent`: maksimal sepuluh attendance terbaru.
- `GET /admin/attendances`
  - Filter aktual: `work_date`, `status`, `is_flagged`, `division_id`.
  - Hasil pagination 15 record.
- `GET /admin/attendances/{id}`

### Media dan attendance — supervisor+

- `GET /admin/media/selfie/{id}/{type}` dengan `type=check_in|check_out`
  - File diambil dari private storage setelah autentikasi/RBAC.
  - Representasi attendance hanya mengekspos `has_check_in_selfie`/`has_check_out_selfie`; path internal private storage tidak diserialisasi.
  - Header: `Cache-Control: private, no-store`, `Pragma: no-cache`, `X-Content-Type-Options: nosniff`.
  - Frontend melakukan authenticated fetch, membuat Blob URL, lalu me-revoke URL saat tidak dipakai.
  - Operator ditolak oleh middleware backend (`403`).

### Attendance dan jadwal — supervisor+

- `POST /admin/attendances/{id}/correct`
  - Body: `status`, optional `check_in_at`, optional `check_out_at`, `reason` wajib.
- `POST /admin/attendances/{id}/resolve-flag`
  - Body: `target_status`, `notes` wajib.
- `GET /admin/schedules`
  - Filter: `schedule_date`, `employee_id`.
- `POST /admin/schedules`
  - Upsert berdasarkan `employee_id + schedule_date`.
  - Body: `employee_id`, `shift_id`, `schedule_date`, optional `notes`, `reason` wajib.

### Master data — superadmin

- Divisi: `GET`, `POST /admin/divisions`; `PUT|PATCH`, `DELETE /admin/divisions/{id}`.
- Pegawai: `GET`, `POST /admin/employees`; `PUT|PATCH /admin/employees/{id}`.
- Reset PIN: `POST /admin/employees/{id}/reset-pin` dengan `new_pin` numerik tepat 6 digit dan `reason`.
- Lokasi: `GET /admin/locations`; `PUT|PATCH /admin/locations/{id}`.
- Shift: `GET`, `POST /admin/shifts`; `PUT|PATCH /admin/shifts/{id}`.
- Audit: `GET /admin/audit-logs` dengan filter `admin_id`, `auditable_type`, `action`, `start_date`, `end_date`.

Seluruh mutasi master menerima `reason` wajib dan menulis audit log atomik. Snapshot reset PIN tidak memuat PIN/hash.

## 6. Batas Implementasi Aktual

Belum tersedia: delete pegawai/shift/lokasi, batch schedule, endpoint review attempt terpisah, report/export, signed URL, atau deployment API. Penambahan endpoint harus didokumentasikan sebelum digunakan frontend.
