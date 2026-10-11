# Product Requirements Document (PRD)

## 📌 Nama Proyek
**Absensi SPPG Pakiskembar 2**

---

## 📖 1. Latar Belakang & Deskripsi Produk
SPPG (Satuan Pelayanan Pemenuhan Gizi) Pakiskembar 2 membutuhkan sistem presensi digital yang andal, cepat, dan mudah dioperasikan langsung di lapangan oleh seluruh staf operasional (dapur, pemorsian, pengemasan, dan distribusi).

Kondisi operasional di lapangan sering kali membuat pegawai tidak memungkinkan untuk memiliki akun email/password individu atau mengunduh aplikasi native. Banyak pegawai menggunakan smartphone pinjaman bersama (kios unit/HP operasional). Oleh karena itu, aplikasi dirancang berbasis **Web App (Next.js)** yang ramah mobile, menggunakan metode identifikasi **Pilih Nama + PIN Pribadi**, divalidasi dengan **Live Camera Selfie** dan **Geofencing GPS Server-Side**, serta aman dipakai bergantian dengan fitur **Auto-Reset State**.

---

## 🎯 2. Tujuan & Sasaran Sistem
1. **Kemudahan Akses Tanpa Akun Tradisional:** Pegawai dapat melakukan absensi dalam hitungan detik tanpa kerumitan registrasi email atau lupa password.
2. **Akurasi Validasi Presensi:** Menghindari manipulasi data dengan memvalidasi koordinat GPS, waktu presensi, dan shift kerja di sisi backend (Server as Single Source of Truth).
3. **Dukungan Penuh Shift Malam (Cross-Day Shift):** Mengakomodasi shift kerja operasional malam (misal 19.00 s.d. 03.00 WIB) dengan penetapan tanggal kerja (`work_date`) yang konsisten.
4. **Keamanan & Perlindungan Privasi:** Penyimpanan foto selfie pada *private storage*, hash PIN yang aman, rate-limiting anti brute-force, dan pembersihan otomatis state di browser setelah sesi absensi selesai.
5. **Akuntabilitas & Audit Trail:** Setiap koreksi absensi manual dan perubahan master data oleh administrator tercatat secara detail dalam log audit.

---

## 👥 3. Profil Pengguna & Hak Akses (User Roles)

### 3.1. Pegawai Operasional (Employee / Staff)
- Mengakses portal presensi publik melalui browser ponsel pribadi maupun ponsel pinjaman di unit.
- Tidak memiliki akun login email/password.
- Terdaftar di master data pegawai dengan status aktif dan memiliki kode PIN terenkripsi.
- Hak Akses: Melakukan Check-in, Check-out, dan melihat feedback status absensi langsung saat itu.

### 3.2. Administrator / Tim Manajemen (Admin)
- Mengakses portal manajemen `/admin` melalui autentikasi akun kredensial (Email & Password terproteksi RBAC).
- Hak Akses:
  - Mengelola Master Divisi dan Master Pegawai (termasuk reset PIN).
  - Mengelola Master Shift dan Penugasan Jadwal Kerja.
  - Memantau dashboard kehadiran real-time harian/mingguan.
  - Meninjau dan memproses absensi bertanda `needs_review` (flagged anomalies).
  - Melakukan koreksi manual riwayat absensi dengan menyertakan alasan audit.
  - Mengekspor laporan rekapitulasi kehadiran (Excel/CSV/PDF).
  - Melihat riwayat log audit sistem.

---

## 🛠️ 4. Ruang Lingkup & Kebutuhan Fitur (Feature Scope)

### 4.1. Modul Presensi Pegawai (No-Account Flow)
1. **Layar Pemilihan Pegawai:**
   - Pencarian cepat dan filter berdasarkan divisi.
   - Hanya menampilkan pegawai yang berstatus `aktif`.
2. **Verifikasi PIN:**
   - Input PIN numerik tepat 6 digit dengan keypad virtual yang responsif dan aman.
   - Dibatasi dengan rate limiting di backend (maksimal 5 request per IP/pegawai dalam 15 menit) dan lock pegawai setelah 5 PIN gagal.
3. **Capture Kamera & Geolocation Real-Time:**
   - Akses kamera depan browser secara langsung (live video stream frame capture). Dilarang memilih dari file manager / galeri.
   - Pengambilan koordinat GPS (`latitude`, `longitude`, `accuracy` meter).
4. **Validasi & Penetapan Status Backend:**
   - Perhitungan jarak titik presensi terhadap lokasi unit SPPG Pakiskembar 2.
   - Evaluasi jadwal shift pegawai untuk menentukan aksi (Check-in atau Check-out) dan status kehadiran (`on_time`, `late`, `early_leave`, `flagged`).
5. **Feedback & Auto-Reset:**
   - Menampilkan notifikasi visual berhasil/gagal beserta detail jam dan status.
   - Dalam hitungan beberapa detik (misal 5 detik) atau tombol "Selesai", state identitas pegawai dibersihkan sepenuhnya dari memory/browser dan kembali ke layar pemilihan nama awal.

### 4.2. Modul Manajemen Master Data & Divisi (Admin)
1. **Master Divisi:** Nama divisi (Dapur, Pengemasan, Distribusi, Kebersihan, Administrasi), deskripsi, status aktif.
2. **Master Pegawai:** NIP/Kode Pegawai, Nama Lengkap, Divisi, Nomor Kontak, Status Aktif, Set/Reset PIN (hash).
3. **Master Lokasi Unit SPPG:** Titik koordinat pusat (`latitude`, `longitude`), radius geofence default (meter), alamat unit.

### 4.3. Modul Manajemen Shift & Jadwal
1. **Master Shift:**
   - Nama Shift (Pagi, Siang, Malam/Cross-day).
   - Jam Masuk (`start_time`), Jam Pulang (`end_time`), Batas Awal Check-in (`check_in_open`), Batas Akhir Check-in, Toleransi Keterlambatan (`late_tolerance_minutes`), Batas Awal Check-out, Flag `is_cross_day`.
2. **Penugasan Jadwal (Schedules):**
   - Penugasan shift harian atau pola berulang untuk pegawai.

### 4.4. Modul Rekapitulasi, Koreksi & Audit Trail
1. **Dashboard Monitoring Real-time:** Jumlah pegawai hadir, terlambat, belum hadir, dan absensi yang butuh review (`needs_review`).
2. **Koreksi Manual Absensi:** Admin dapat mengubah jam check-in/out, mengubah status, atau memverifikasi absensi yang di-flag, dengan kewajiban mengisi `correction_reason`.
3. **Audit Log:** Menyimpan catatan `who`, `when`, `action`, `old_values`, `new_values`, dan `ip_address` untuk setiap tindakan penting.
4. **Ekspor Laporan (Backlog):** Format Excel/CSV/PDF belum memiliki endpoint atau UI pada implementasi saat ini.

---

## 🔒 5. Batasan Teknis & Mitigasi Keamanan
1. **Penyimpanan Foto Selfie:** Disimpan di direktori privat (`storage/app/private/selfies/`) dan hanya diakses Supervisor atau Superadmin melalui proxy Bearer terautentikasi; Operator ditolak oleh authorization backend. Frontend menampilkan hasil fetch sebagai Blob URL sementara dan me-revoke URL setelah digunakan.
2. **Pencegahan Duplikasi Presensi:** Menggunakan row lock MySQL/InnoDB, transaksi atomik, dan constraint unik agar pegawai tidak dapat membuat dua sesi sah untuk shift/hari kerja yang sama.
3. **Disclaimer GPS & Kamera:** Sistem mencatat parameter akurasi GPS dan live frame capture, tetapi tidak kebal 100% dari mock location, perangkat rooted, atau manipulasi hardware. Verifikasi visual dan audit menjadi mitigasi tambahan.

---

## ❓ 6. Poin Keputusan & Konfirmasi yang Tertunda (Open Decisions)

> [!NOTE]
> Poin-poin berikut telah dicatat sebagai item yang dapat dikonfigurasi dan menunggu konfirmasi lanjutan sebelum tahap implementasi backend/frontend:

| No | Topik | Pertanyaan / Opsi Konfirmasi | Status |
| :--- | :--- | :--- | :--- |
| 1 | **Panjang Digit PIN** | PIN numerik tepat 6 digit. | *Ditetapkan pada Fase 4B.2B* |
| 2 | **Radius Geofence SPPG** | Berapa radius toleransi meter default untuk lokasi SPPG Pakiskembar 2 (misal: 50 meter atau 100 meter)? | *Pending Konfirmasi (Default seeder: 50 meter)* |
| 3 | **Pola Penugasan Shift** | Apakah pegawai selalu ditugaskan shift secara fleksibel harian (jadwal dinamis) atau memiliki shift tetap per divisi? | *Pending Konfirmasi (Disediakan opsi default shift + jadwal spesifik)* |
| 4 | **Toleransi Keterlambatan Shift Malam** | Apakah toleransi keterlambatan shift malam sama (15 menit) atau berbeda dengan shift siang? | *Pending Konfirmasi (Dikonfigurasi per master shift)* |
