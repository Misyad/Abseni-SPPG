# Absensi SPPG Pakiskembar 2

Sistem Presensi dan Manajemen Kehadiran Pegawai **Satuan Pelayanan Pemenuhan Gizi (SPPG) Pakiskembar 2**.

---

## 🌟 Ringkasan Proyek

Aplikasi ini dikembangkan untuk mencatat dan mengelola kehadiran pegawai SPPG Pakiskembar 2 dengan fokus pada kemudahan operasional lapangan, akurasi geolokasi, bukti kehadiran visual (live selfie), dan penanganan shift kerja operasional (termasuk shift malam lintas hari).

Pegawai operasional dapat melakukan presensi tanpa perlu mendaftarkan/memiliki akun bertipe username/password tradisional. Presensi dilakukan dengan alur pemilihan nama, verifikasi PIN yang aman, live camera selfie, dan pengiriman koordinat geolokasi GPS ke server. Sementara itu, tim manajemen dan administrator menggunakan portal admin terautentikasi (RBAC) untuk mengelola data master, jadwal, monitoring real-time, koreksi manual, dan laporan.

---

## 🏗️ Tech Stack

| Komponen | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Frontend** | [Next.js](https://nextjs.org/) (App Router), React, TypeScript | Mode Strict, Mobile-First UI |
| **Styling & UI** | [Tailwind CSS](https://tailwindcss.com/) & [shadcn/ui](https://ui.shadcn.com/) | Desain modern, responsif, dan konsisten |
| **Backend API** | [Laravel](https://laravel.com/) (PHP 8.2+) | RESTful API, Form Requests, Sanctum (Admin) |
| **Database** | MySQL 8.0 (InnoDB Engine) | Transaksi ACID, relasi FK eksplisit, Timezone `Asia/Jakarta` |
| **Penyimpanan Berkas** | Private Storage (Local Storage / S3 Private) | Selfie via proxy Bearer terautentikasi; frontend memakai Blob URL sementara |
| **Infrastruktur** | Docker & Docker Compose | Containerisasi terstandarisasi untuk Frontend, Backend, & DB |

---

## 🧭 Alur Presensi Pegawai (No-Account Flow)

Sistem dirancang aman digunakan melalui smartphone pribadi maupun smartphone bersama/pinjaman (kios unit):
1. **Pilih Nama:** Pegawai memilih nama sendiri dari daftar divisi/pegawai aktif.
2. **Input PIN:** Memasukkan PIN rahasia (disimpan dalam bentuk hash di backend dengan rate limiting).
3. **Izin Perangkat:** Memberikan izin akses browser untuk kamera dan lokasi (GPS).
4. **Live Selfie:** Mengambil swafoto langsung dari kamera browser (dilarang unggah dari galeri file).
5. **Kirim Koordinat & Akurasi:** Mengirimkan koordinat latitude, longitude, dan parameter akurasi GPS ke backend.
6. **Validasi Server:** Backend memvalidasi shift aktif, toleransi radius geofence, waktu operasional, dan menentukan status (`on_time`, `late`, `early_leave`, atau `needs_review`).
7. **Tampilan Hasil:** Menampilkan konfirmasi status absensi secara jelas.
8. **Auto Reset State:** State identitas sementara dan formulir langsung dibersihkan dan dialihkan kembali ke layar awal untuk keamanan giliran pegawai berikutnya.

---

## ⏰ Aturan Shift & Shift Malam (Overnight Shift)

- Sistem mendukung shift kerja malam yang melintasi pergantian hari (contoh: **Pukul 19.00 s.d. 03.00 keesokan hari**).
- Penentuan `work_date` mengacu pada **tanggal dimulainya jadwal shift** yang bersangkutan:
  - Check-in: 10 Oktober 2026, 19:03 WIB $\rightarrow$ `work_date`: `2026-10-10`.
  - Check-out: 11 Oktober 2026, 03:02 WIB $\rightarrow$ `work_date`: `2026-10-10`.
- **Bukan Algoritma Ganjil-Genap:** Penentuan sesi masuk vs pulang ditentukan secara deterministik melalui jadwal shift aktif, window terkonfigurasi, dan riwayat presensi `work_date`.
- **Deteksi Anomali:** Jadwal hilang, ambigu, atau request di luar window dicatat sebagai `attendance_attempts`, bukan sesi attendance dengan relasi rekaan. Attendance valid yang marginal dapat berstatus `needs_review`.

---

## 📁 Struktur Direktori

```text
.
├── AGENTS.md                 # Panduan & aturan kerja untuk AI Coding Agent
├── README.md                 # Dokumentasi umum & roadmap proyek
├── docs/                     # Spesifikasi teknis sistem
│   ├── PRD.md                # Product Requirements Document
│   ├── BUSINESS_RULES.md     # Logika bisnis, shift malam, geofencing, & audit
│   ├── DESIGN_SPEC.md        # Spesifikasi alur UI/UX & komponen antarmuka
│   ├── DATABASE_SCHEMA.md    # Skema tabel MySQL InnoDB, indexing, & relasi
│   ├── API_SPEC.md           # Kontrak RESTful API (Payload, Response, Error codes)
│   ├── API_URL_STRATEGY.md   # Strategi URL API per lingkungan
│   └── STAGING_READINESS.md  # Prasyarat sebelum staging diaktifkan
├── frontend/                 # Source code frontend (Next.js + shadcn/ui)
├── backend/                  # Source code backend (Laravel REST API)
├── infra/                    # Konfigurasi container Docker & Docker Compose
└── Jenkinsfile               # Pipeline CI Jenkins tanpa deployment
```

---

## 🗺️ Roadmap Pengembangan

- [x] **Fase 1: Perencanaan & Spesifikasi Sistem**
  - Penyusunan seluruh dokumen spesifikasi (`PRD`, `BUSINESS_RULES`, `DESIGN_SPEC`, `DATABASE_SCHEMA`, `API_SPEC`).
  - Penyelarasan batasan arsitektur dan keamanan.
- [x] **Fase 2: Backend Core & Database Setup**
  - Migrasi skema database, seeder data master, hashing PIN, geofence server-side, rate limiter, dan endpoint API tersedia.
- [x] **Fase 3: Frontend Client & Admin Portal**
  - Alur presensi pegawai dan portal admin tersedia.
- [x] **Fase 4A.1: Testing & Dockerisasi Awal**
  - Automated backend tests, frontend typecheck/build, Dockerfile, dan Docker Compose tersedia.
- [x] **Fase 4B: Persiapan CI Jenkins**
  - Audit Git/Docker, MySQL test terisolasi, lint/typecheck, image tagging, Jenkinsfile CI, dan dokumentasi kesiapan staging.
  - Tidak mencakup registry push atau deployment staging/production.
- [x] **Fase 4B.1: Final Review Persiapan CI**
  - Final review source, staging awal, dan strategi URL API selesai disiapkan.
  - Eksekusi CI tetap menunggu commit awal, remote resmi, serta Jenkins/agent Docker terverifikasi.
- [ ] **Fase 4B.2A: Pre-Commit Blocker Remediation**
  - Perbaikan timezone, attendance window, overnight resolver, locking, cleanup selfie, anomaly attempt, rate limit, RBAC, audit atomik, dan private Blob proxy tersedia untuk review.
  - Frontend lint/typecheck/build dijalankan lokal pada fase ini; hasil aktual dicatat pada laporan review.
  - Backend/MySQL/concurrency hanya boleh dinyatakan lulus setelah runtime PHP/Docker tersedia dan suite benar-benar dijalankan.
- [ ] **Fase 4B.2B: Final Policy Alignment & Runtime Validation**
  - Kebijakan final: PIN numerik tepat 6 digit; selfie privat hanya Supervisor dan Superadmin.
  - Validasi runtime backend/MySQL/concurrency tetap menunggu runtime Docker/Linux yang terverifikasi.

---

## 📚 Dokumentasi Lengkap

1. [Panduan AI Agent (AGENTS.md)](file:///d:/Abseni%20SPPG/AGENTS.md)
2. [Product Requirements Document (PRD.md)](file:///d:/Abseni%20SPPG/docs/PRD.md)
3. [Business Rules (BUSINESS_RULES.md)](file:///d:/Abseni%20SPPG/docs/BUSINESS_RULES.md)
4. [Design Specification (DESIGN_SPEC.md)](file:///d:/Abseni%20SPPG/docs/DESIGN_SPEC.md)
5. [Database Schema (DATABASE_SCHEMA.md)](file:///d:/Abseni%20SPPG/docs/DATABASE_SCHEMA.md)
6. [API Specification (API_SPEC.md)](file:///d:/Abseni%20SPPG/docs/API_SPEC.md)
7. [Strategi URL API (API_URL_STRATEGY.md)](file:///d:/Abseni%20SPPG/docs/API_URL_STRATEGY.md)
8. [Kesiapan Staging (STAGING_READINESS.md)](file:///d:/Abseni%20SPPG/docs/STAGING_READINESS.md)
