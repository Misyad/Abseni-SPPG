# Design Specification (DESIGN_SPEC.md)

## 📌 Nama Proyek
**Absensi SPPG Pakiskembar 2**

---

## 🎨 1. Sistem Desain & Visual Identity

Aplikasi mengusung antarmuka yang bersih, modern, kontras tinggi, dan berorientasi *mobile-first* untuk memudahkan operasional staf lapangan dalam kondisi pencahayaan dapur/luar ruangan.

### 1.1. Color Tokens (Tailwind CSS)
- **Brand Primary:** Emerald 600 (`#059669`) / Emerald 700 (`#047857`) — Melambangkan kesegaran gizi, profesionalitas, dan keberhasilan.
- **Brand Accent / Warning:** Amber 500 (`#f59e0b`) / Orange 600 (`#ea580c`) — Untuk status terlambat, shift malam, dan item `needs_review`.
- **Status Presence:**
  - `on_time` / Hadir: Emerald 500 (`#10b981`)
  - `late` / Terlambat: Amber 500 (`#f59e0b`)
  - `early_leave` / Pulang Awal: Blue 500 (`#3b82f6`)
  - `needs_review` / Flagged: Purple 500 (`#a855f7`)
  - `absent` / Ditolak: Rose 600 (`#e11d48`)
- **Neutrals & Surfaces:**
  - Background (Light): Slate 50 (`#f8fafc`)
  - Card / Surfaces: White (`#ffffff`)
  - Border: Slate 200 (`#e2e8f0`)
  - Text Primary: Slate 900 (`#0f172a`)
  - Text Muted: Slate 500 (`#64748b`)

### 1.2. Tipografi
- **Primary Font:** Inter / Plus Jakarta Sans
- **Skala Tipografi:**
  - `Display / Clock`: 36px – 48px (Bold, Tabular Numbers untuk jam real-time)
  - `Heading 1`: 24px – 28px (Bold)
  - `Heading 2`: 18px – 20px (SemiBold)
  - `Body / Button`: 14px – 16px (Regular / Medium)
  - `Caption / Badge`: 12px (Medium)

---

## 📱 2. Alur Antarmuka Pegawai (Presensi No-Account)

Dirancang khusus untuk layar smartphone dengan target sentuh (*touch target*) minimal 48x48px:

```text
[1. Pilih Nama] ──> [2. Masukkan PIN] ──> [3. Kamera & GPS] ──> [4. Hasil Absensi] ──> [5. Auto-Reset]
```

### 2.1. Layar 1: Pemilihan Divisi & Nama Pegawai (`/`)
- **Header:** Logo SPPG Pakiskembar 2, Jam Digital Real-Time (WIB), dan status shift aktif saat ini.
- **Search Bar & Filter Tab Divisi:** Filter cepat (Semua, Dapur, Pemorsian, Distribusi, Kebersihan).
- **Daftar Pegawai:** Grid kartu atau daftar list nama pegawai aktif dengan avatar inisial besar. Tap pada nama akan membuka dialog/layar PIN.

### 2.2. Layar 2: Masukkan PIN Pegawai
- **Nama Pegawai Terpilih:** Menampilkan nama dan divisi pegawai.
- **Indikator PIN:** Enam lingkaran masked dot; submit aktif hanya pada tepat 6 digit.
- **Virtual Numeric Keypad:** Tombol angka 1-9, 0, tombol backspace, submit, dan tombol batal.
- **Feedback Validasi:** Animasi shake dan pesan error generik; jumlah percobaan tersisa tidak dibocorkan.

### 2.3. Layar 3: Live Kamera & Deteksi Lokasi
- **Live Video Viewfinder:** Tampilan kamera depan rasio 1:1 atau 3:4 dengan panduan garis oval wajah (*face guide overlay*).
- **GPS Status Badge:**
  - 🟢 *Lokasi Terkunci (Akurasi: ±8m)*
  - 🟡 *Mencari Sinyal GPS...*
  - 🔴 *Izin Lokasi Ditolak / Di Luar Jangkauan*
- **Tombol "Ambil Selfie & Kirim Absensi":** Tombol utama berukuran besar dengan konfirmasi visual saat ditekan.
- *Catatan:* Tidak ada opsi upload file lokal atau galeri.

### 2.4. Layar 4: Hasil Absensi (Success / Flagged Feedback)
- **Kartu Hasil:**
  - Ikon status (Centang hijau untuk Berhasil / Ikon warning ungu untuk `needs_review`).
  - Nama Pegawai & Divisi.
  - Tipe Aksi: **Check-in** atau **Check-out**.
  - Tanggal Kerja (`work_date`): e.g., *10 Oktober 2026*.
  - Waktu Server: e.g., *19:03 WIB*.
  - Status Kehadiran: e.g., *Tepat Waktu (On-Time)* / *Terlambat 8 Menit*.
- **Progress Bar Auto-Reset:** Indikator hitung mundur 5 detik menuju reset layar.
- **Tombol "Selesai":** Tombol untuk segera kembali ke halaman awal tanpa menunggu hitung mundur.

---

## 💻 3. Alur Antarmuka Portal Admin (`/admin`)

Dibangun menggunakan komponen modern **shadcn/ui** (Sidebar, Data Table, Dialog, Dropdown, Badges, Tabs):

### 3.1. Halaman Login Admin (`/admin/login`)
- Form autentikasi email dan password terenkripsi dengan proteksi CSRF dan rate limiter.

### 3.2. Dashboard Utama (`/admin/dashboard`)
- **Metrik Ringkasan Hari Ini:**
  - Total Pegawai Terjadwal
  - Hadir Tepat Waktu
  - Terlambat
  - Perlu Review (`needs_review`)
  - Belum Hadir / Alpha
- **Live Attendance Stream:** Feed presensi terbaru lengkap dengan badge status dan lokasi. Operator tidak memuat atau melihat selfie. Supervisor dan Superadmin mengambil selfie lewat authenticated fetch Bearer ke proxy privat, menampilkannya sebagai Blob URL, lalu me-revoke URL ketika modal berubah/ditutup.

### 3.3. Master Data dan Penjadwalan
- API RBAC untuk divisi, pegawai, lokasi, shift, PIN, dan jadwal tersedia.
- Halaman frontend khusus pengelolaan master/jadwal belum tersedia pada implementasi saat ini.

### 3.4. Review Center & Koreksi Absensi (`/admin/dashboard`)
- Tab review menampilkan attendance dengan `is_flagged`/`needs_review`.
- **Modal Detail Absensi:** peta koordinat, status, dan IP client tersedia untuk Operator, Supervisor, dan Superadmin; selfie check-in privat hanya tersedia untuk Supervisor dan Superadmin.
- **Modal Koreksi Manual:** status baru dan alasan audit wajib; hanya supervisor/superadmin.
- Attempt tanpa jadwal dihitung dalam metrik anomali, tetapi daftar/detail attempt admin belum tersedia.

### 3.5. Audit dan Ekspor
- Endpoint audit log tersedia untuk `superadmin`; UI explorer belum tersedia.
- Endpoint dan UI ekspor laporan belum diimplementasikan.

---

## 📐 4. Spesifikasi Komponen & Aksesibilitas (A11y)

1. **State Handling:**
   - Semua tombol async memiliki status `loading` dengan indikator spinner.
   - Tabel data memiliki state `EmptyState` yang informatif dan state `SkeletonLoader` saat fetching data.
2. **Error Boundaries:**
   - Tangani kegagalan izin browser (kamera/lokasi diblokir) dengan petunjuk visual cara mengaktifkan izin di Chrome/Safari ponsel.
3. **Contrast Ratio:** Seluruh teks dan tombol memenuhi standar kontras minimal WCAG AA (rasio $\ge$ 4.5:1).

---

## ❓ 5. Keputusan yang Masih Perlu Dikonfirmasi (Pending Decisions)

| No | Komponen UI | Pertanyaan Konfirmasi | Opsi / Rekomendasi |
| :--- | :--- | :--- | :--- |
| 1 | **Tampilan Daftar Pegawai** | Apakah daftar pegawai di layar utama ditampilkan berupa Card Grid dengan foto atau Simple List? | *Rekomendasi: List berukuran besar dengan badge divisi untuk kemudahan scroll di HP.* |
| 2 | **Durasi Countdown Reset** | Berapa detik countdown auto-reset setelah absensi berhasil (3 detik, 5 detik, atau 10 detik)? | *Rekomendasi: 5 detik dengan tombol interupsi "Selesai".* |
| 3 | **Peta di Portal Admin** | Apakah preview lokasi pada detail absensi admin menggunakan Leaflet/OpenStreetMap (gratis) atau Google Maps JS API? | *Rekomendasi: Leaflet + OpenStreetMap untuk efisiensi tanpa dependensi API Key berbayar.* |
