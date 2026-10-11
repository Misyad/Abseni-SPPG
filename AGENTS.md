# AGENTS.md — Aturan Kerja & Batasan AI Agent

Dokumen ini berisi panduan, konvensi, dan batasan kerja bagi AI Coding Agent dalam mengembangkan proyek **Absensi SPPG Pakiskembar 2**.

---

## 📌 1. Gambaran Proyek
- **Nama Proyek:** Absensi SPPG Pakiskembar 2
- **Tujuan:** Sistem pencatatan kehadiran presensi pegawai Satuan Pelayanan Pemenuhan Gizi (SPPG) berbasis selfie langsung dan geofencing tanpa mengharuskan pegawai login akun penuh (menggunakan alur Nama + PIN), serta portal manajemen khusus admin.
- **Frontend:** Next.js (App Router), React, TypeScript (Strict Mode), Tailwind CSS, shadcn/ui.
- **Backend:** Laravel RESTful API (PHP 8.2+), Eloquent ORM, Laravel Sanctum (untuk Admin).
- **Database:** MySQL 8.0 (InnoDB engine, strict mode, timezone `+07:00` / `Asia/Jakarta`).
- **Penyimpanan Berkas:** Private Storage (Selfie & dokumen tidak bisa diakses publik secara bebas).
- **Infrastruktur:** Docker & Docker Compose.

---

## 🎯 2. Prinsip & Aturan Pengembangan
1. **Bahasa & Dokumentasi:**
   - Komunikasi dan penulisan dokumen menggunakan **Bahasa Indonesia** yang formal, jelas, dan profesional.
   - Penamaan teknis dalam kode (variabel, fungsi, class, method, endpoint, kolom database) wajib menggunakan **Bahasa Inggris** baku (`camelCase` untuk TS/JS, `snake_case` untuk DB & PHP fields/methods).
2. **Backend sebagai Single Source of Truth:**
   - Jangan pernah mempercayai validasi status, radius jarak, atau waktu lokal yang dikirimkan oleh browser client.
   - Semua penentuan shift, status kedatangan (`on_time`, `late`, `early_leave`, `flagged`), perhitungan jarak geofence (formula Haversine/Vincenty), dan penetapan `work_date` wajib dieksekusi di backend.
3. **Keamanan & Privasi Data:**
   - PIN pegawai wajib di-hash menggunakan algoritma aman (misal `bcrypt` / `argon2id`) dan tidak boleh disimpan di browser (localStorage, sessionStorage, cookie).
   - Terapkan rate limiting ketat pada verifikasi PIN untuk mencegah brute-force.
   - File selfie disimpan di private storage lokal atau S3 private bucket, hanya dapat dilihat oleh admin melalui temporary signed URL atau proxy endpoint terautentikasi.
   - Semua mutasi data penting (koreksi absensi, perubahan master data, penyesuaian PIN) wajib mencatat log audit (`audit_logs`).
   - Setiap operasi yang melibatkan multi-tabel atau perubahan status penting wajib dibungkus dalam **Database Transaction** (`DB::transaction`).
4. **Logika Shift & Hari Kerja (`work_date`):**
   - Shift malam (contoh: 19.00 s.d. 03.00 keesokan hari) menggunakan `work_date` yang mengacu pada tanggal dimulainya shift.
   - Dilarang keras menggunakan algoritma ganjil=masuk, genap=pulang.
   - Jika terjadi kejanggalan atau ambiguitas status sesi, sistem wajib menandai status record sebagai `needs_review` / `flagged` dan meminta verifikasi admin, bukan membuat sesi liar.
5. **Transparansi Keterbatasan:**
   - Jangan mengklaim sistem geofencing dan selfie kebal 100% dari kecurangan (misal: fake GPS, rooted device, atau manipulasi hardware kamera). Sistem menyediakan mitigasi teknis dan pencatatan parameter akurasi GPS untuk audit.

---

## 🚦 3. Batasan Tahapan Proyek (Phase Constraints)
- **Tahap Saat Ini: Fase 4B — Persiapan CI/CD Jenkins (CI saja).**
  - Implementasi backend, frontend, dan Docker Fase 1–4A.1 telah tersedia untuk diaudit.
  - Pipeline hanya boleh melakukan checkout, lint/format, testing terisolasi, build image, validasi, dan publikasi laporan pengujian.
  - Deployment staging/production, perubahan Dockerhost, VM/LXC, Cloudflare Tunnel, webhook production, SSH credential, registry push, database production, Docker prune, dan penghapusan volume dilarang tanpa instruksi serta persetujuan terpisah.
  - Secret tidak boleh disimpan dalam repository atau Jenkinsfile; gunakan environment lokal yang diabaikan Git dan Jenkins Credentials pada tahap yang disetujui.
  - Hasil verifikasi hanya boleh dilaporkan berdasarkan eksekusi aktual; keterbatasan environment wajib dicatat.
  - Semua dokumen di folder `docs/`, `AGENTS.md`, dan `README.md` harus konsisten satu sama lain.

---

## 📂 4. Referensi Dokumen
Sebelum melangkah ke tahap implementasi, AI wajib membaca dan merujuk dokumen-dokumen berikut:
- [README.md](file:///d:/Abseni%20SPPG/README.md) — Rangkuman arsitektur dan roadmap proyek.
- [docs/PRD.md](file:///d:/Abseni%20SPPG/docs/PRD.md) — Product Requirements Document.
- [docs/BUSINESS_RULES.md](file:///d:/Abseni%20SPPG/docs/BUSINESS_RULES.md) — Aturan bisnis presensi, shift malam, dan validasi.
- [docs/DESIGN_SPEC.md](file:///d:/Abseni%20SPPG/docs/DESIGN_SPEC.md) — Spesifikasi antarmuka UI/UX dan alur layar.
- [docs/DATABASE_SCHEMA.md](file:///d:/Abseni%20SPPG/docs/DATABASE_SCHEMA.md) — Struktur tabel, indeks, foreign keys, dan skema audit.
- [docs/API_SPEC.md](file:///d:/Abseni%20SPPG/docs/API_SPEC.md) — Kontrak API RESTful lengkap (Request, Response, Error Codes).
