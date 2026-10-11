# Aturan Bisnis (Business Rules)

## 1. Identifikasi Pegawai dan PIN

1. Pegawai memilih identitas lalu memasukkan PIN numerik tepat 6 digit. PIN hanya berada dalam state memori React dan tidak disimpan di `localStorage`, `sessionStorage`, atau cookie.
2. PIN disimpan sebagai hash aman. Endpoint verifikasi dibatasi **5 request per kombinasi employee ID dan IP selama 15 menit**.
3. Kegagalan PIN dicatat dengan row lock pada pegawai. Lima kegagalan mengunci verifikasi selama 15 menit.
4. Pesan kegagalan tidak membedakan pegawai tidak ada, tidak aktif, atau PIN salah.
5. Pre-check PIN memakai resolver jadwal yang sama dengan submit. Kamera hanya dibuka jika jadwal dan window menghasilkan satu aksi deterministik.

## 2. Strategi Waktu

- Zona waktu bisnis Laravel: `Asia/Jakarta`.
- Session timezone MySQL: `+07:00` melalui `DB_TIMEZONE`.
- Timestamp bisnis dan `work_date` dihitung backend. Nilai waktu browser tidak dipercaya.
- `work_date` selalu tanggal mulai shift. Shift `19:00–03:00` yang check-out pukul `03:02` hari berikutnya tetap memakai tanggal saat pukul `19:00` dimulai.
- Data `DATETIME` presensi disimpan sebagai waktu WIB. Konfigurasi aplikasi dan session DB wajib tetap konsisten.

## 3. Jadwal dan Window Shift

Setiap shift memiliki parameter:

| Parameter | Default | Arti |
|---|---:|---|
| `check_in_window_start_minutes` | 60 | Window check-in dibuka sebelum `start_time`. |
| `check_in_window_end_minutes` | 240 | Window check-in ditutup setelah `start_time`. |
| `late_tolerance_minutes` | 15 | Check-in sampai batas ini tetap `on_time`; setelahnya `late`. |
| `check_out_window_start_minutes` | 30 | Window check-out dibuka sebelum `end_time`. |
| `check_out_window_end_minutes` | 240 | Window check-out ditutup setelah `end_time`. |

Batas awal dan akhir bersifat inklusif. Aksi ditentukan sebagai berikut:

1. Cari jadwal pegawai pada rentang tanggal kemarin, hari ini, dan besok.
2. Bentuk interval shift dari `schedule_date`, `start_time`, `end_time`, dan `is_cross_day`; tidak ada cutoff jam hardcoded.
3. Jika ada attendance terbuka, hanya window check-out yang berlaku.
4. Jika belum ada attendance, hanya window check-in yang berlaku.
5. Nol kandidat menghasilkan `SCHEDULE_MISSING` atau `OUTSIDE_ATTENDANCE_WINDOW`.
6. Lebih dari satu kandidat menghasilkan `SCHEDULE_AMBIGUOUS`.
7. Attendance lengkap menghasilkan `ALREADY_COMPLETED`; retry tidak membuat record atau selfie baru.
8. Sistem tidak memilih shift terdekat dan tidak memakai algoritma ganjil/genap.

## 4. Anomaly Attempt

Penolakan karena jadwal hilang, ambigu, atau di luar window disimpan di `attendance_attempts`, bukan `attendances`.

- Attempt memiliki relasi pegawai nyata, reason code, koordinat mentah, akurasi, IP, User-Agent, dan waktu server.
- Attempt tidak mengarang shift/lokasi dan tidak membuat sesi attendance sah.
- Selfie tidak disimpan untuk request yang ditolak sebelum tahap persistence.

## 5. Geofence dan Selfie

- Haversine dihitung backend terhadap lokasi aktif.
- Jarak di atas radius sampai tambahan 15 meter ditandai `needs_review`; jarak lebih jauh ditolak.
- `accuracy` GPS disimpan untuk audit. Ambang penolakan accuracy belum ditetapkan; sistem tidak mengarang ambang.
- Selfie disimpan di private disk. Akses admin memakai authenticated proxy; frontend melakukan fetch Bearer, membuat Blob URL, lalu revoke saat detail ditutup/berubah.
- Cache selfie dinonaktifkan. Token dan URL privat tidak boleh dilog.
- Geofence dan selfie merupakan mitigasi, bukan jaminan kebal fake GPS, rooted device, atau manipulasi hardware.

## 6. Transaksi dan Concurrency

- Submit memakai transaksi MySQL, row lock pegawai, jadwal/attendance terkait, serta unique constraint `employee_id + work_date + shift_id`.
- Validasi jadwal, window, geofence, dan duplikasi dilakukan sebelum menyimpan selfie.
- Jika transaksi gagal setelah file baru dibuat, file baru dihapus secara kompensatif. File attendance valid tidak pernah dihapus.
- Kegagalan cleanup dicatat `critical` dan dinaikkan sebagai error agar dapat ditangani operator.
- Klaim concurrency harus dibuktikan pada MySQL/InnoDB; SQLite bukan bukti locking.

## 7. Status Attendance

Attendance sah memakai: `on_time`, `late`, `early_leave`, `absent`, `needs_review`, atau `corrected`.
`SCHEDULE_MISSING`, `SCHEDULE_AMBIGUOUS`, dan `OUTSIDE_ATTENDANCE_WINDOW` adalah reason attempt, bukan status attendance sah.

## 8. RBAC Admin

| Role | Kewenangan |
|---|---|
| `operator` | Membaca dashboard, attendance, dan detail attendance tanpa foto selfie. |
| `supervisor` | Seluruh hak operator; membaca selfie privat; koreksi/resolve attendance; baca/tulis jadwal. |
| `superadmin` | Seluruh hak supervisor; master divisi, pegawai, lokasi, shift; reset PIN; audit log. |

Proteksi berada pada middleware backend. Penyembunyian tombol frontend hanya UX.

## 9. Audit Trail

- Koreksi/resolve attendance, reset PIN, mutasi pegawai/divisi/lokasi/shift/jadwal wajib menghasilkan audit log.
- Mutasi bisnis dan audit log berada dalam transaksi yang sama.
- Alasan wajib. Log mencatat aktor, aksi, entitas, old/new values relevan, IP, User-Agent, dan waktu.
- PIN, hash PIN, token, dan secret tidak boleh masuk audit log.
