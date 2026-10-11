# Kesiapan Staging � Belum Diaktifkan

Dokumen ini mencatat dependensi sebelum tahap deployment dibuat. Fase 4B hanya menyiapkan CI.

## Keputusan dan akses yang wajib tersedia

| Kebutuhan | Status saat audit | Syarat sebelum staging |
|---|---|---|
| Remote Git | Belum dikonfigurasi | Tetapkan URL repository resmi; jangan mengarang URL. |
| Riwayat Git | Branch `main` belum memiliki commit | Buat commit awal melalui review manusia, lalu push manual. |
| Lokasi Jenkins | Belum diverifikasi | Catat URL, controller, agent, versi Jenkins, plugin, dan pemilik operasional. |
| Agent CI | Belum diverifikasi | Agent Linux berlabel `docker`, Git, Docker Engine, Compose v2, Node/npm bila mengikuti Jenkinsfile saat ini. |
| Docker build | Belum diverifikasi | Gunakan agent terisolasi. Jangan mount Docker socket host bersama tanpa penilaian risiko; pilih agent ephemeral/dedicated atau BuildKit rootless. |
| Registry image | Belum tersedia/disetujui | Tetapkan registry, retensi, scanning, immutable tag, serta Jenkins Credentials. Pipeline saat ini tidak push. |
| Host staging | Belum ditentukan | Dockerhost staging terpisah atau namespace terisolasi; bukan production. |
| SSH deployment | Belum disetujui | Akun deployment non-root, command terbatas, host key pinning, kredensial di Jenkins Credentials. |
| Hostname staging | Belum ditentukan | Tetapkan domain/hostname dan sertifikat TLS. |
| Cloudflare Tunnel | Belum diverifikasi | Pastikan connector dapat mencapai network aplikasi staging tanpa mengekspos MySQL. Perubahan memerlukan persetujuan terpisah. |
| Secret aplikasi | Belum dibuat | APP_KEY dan password DB unik staging melalui secret manager; tidak disimpan di Git/Jenkinsfile. |
| Persistent storage | Desain tersedia | Volume DB dan selfie terpisah, kapasitas/ownership diuji, backup terenkripsi, uji restore. |
| Health check | Definisi tersedia | Verifikasi `/up`, halaman frontend, koneksi DB, timeout, alerting. |
| Rollback | Belum diuji | Simpan tag image immutable sebelumnya; rollback aplikasi tanpa menurunkan schema secara destruktif. |
| Approval production | Belum dibuat | Gate manual, pemisahan kredensial, change record, backup terverifikasi. |

## Kontrak deployment tahap berikutnya

1. CI harus hijau dan image ditandai commit SHA.
2. Push image hanya setelah registry serta credential ID disetujui.
3. Deployment staging memakai manifest/environment terpisah.
4. Migrasi memakai `php artisan migrate --force`; tidak memakai `migrate:fresh`.
5. Backup DB dan selfie dibuat serta diverifikasi sebelum perubahan schema berisiko.
6. Smoke test memeriksa frontend, `/up`, autentikasi admin, dan satu alur presensi uji.
7. Rollback mengembalikan image sebelumnya; rollback database wajib berupa prosedur khusus yang ditinjau.
8. Production memerlukan instruksi dan approval terpisah.

## Larangan aktif

Tidak ada webhook production, SSH credential, Dockerhost mutation, Cloudflare mutation, registry push, staging deployment, atau production deployment pada Fase 4B.
## Strategi URL API Fase 4B.1

Strategi lengkap tersedia pada [API_URL_STRATEGY.md](file:///d:/Abseni%20SPPG/docs/API_URL_STRATEGY.md).

- Image CI, staging, dan production memakai same-origin `/api/v1`.
- Reverse proxy lingkungan meneruskan `/api/v1` ke Laravel.
- `localhost` hanya sah untuk development pada browser di workstation yang sama.
- Hostname staging dan production belum ditentukan serta tidak dimasukkan ke repository.
- Akses dari HP memerlukan hostname HTTPS/reverse proxy yang disetujui terpisah.

## Review advisory frontend Fase 4B.1

Audit 11 Oktober 2026 pada lockfile aktual setelah penyelarasan Fase 4B.2B:

| Cakupan | Critical | High | Moderate | Rantai utama |
|---|---:|---:|---:|---|
| Seluruh dependency | 0 | 8 | 3 | Next/PostCSS; ESLint Next/fast-glob/micromatch/braces; Tailwind/chokidar/fast-glob/PostCSS tooling. |
| Production (`--omit=dev`) | 0 | 1 | 1 | `next@15.5.27` membawa nested `postcss` rentan (`<=8.5.22`). |

Versi paket terdampak aktual dan jalur perbaikan npm:

| Rantai | Versi aktual | Advisory langsung | Perbaikan yang ditawarkan npm | Kompatibilitas |
|---|---|---|---|---|
| `next` → nested `postcss` | `next@15.5.27`, `postcss@8.4.31` | `GHSA-qx2v-qp2m-jg93`, `GHSA-6g55-p6wh-862q`, `GHSA-fxqj-rqcc-2cmp`, `GHSA-r28c-9q8g-f849` | `next@16.4.0` | Major; App Router, ESLint, Node, dan build wajib diuji terpisah. Rilis `15.5.11` dan `15.5.27` yang diperiksa sama-sama membawa `postcss@8.4.31`. |
| `eslint-config-next` | `15.5.27` | Turunan `@next/eslint-plugin-next` → `fast-glob` → `micromatch`/`braces` | npm menawarkan `eslint-config-next@14.2.35` | Downgrade major tidak selaras dengan `next@15.5.27`; tidak diterapkan. |
| `tailwindcss` tooling | `tailwindcss@3.4.19`, `chokidar@3.6.0`, `braces@3.0.3`, `micromatch@4.0.8`, `postcss-nested@6.2.0`, `postcss-selector-parser@6.1.4` | `GHSA-vfj7-8cjw-p6xm`, `GHSA-rj75-hqrm-r3gf` | `tailwindcss@4.3.3` | Major; migrasi konfigurasi/PostCSS dan regresi UI wajib diuji terpisah. |

Keputusan:

1. Tidak menjalankan `npm audit fix --force`.
2. Upgrade Next 16 dan Tailwind 4 harus menjadi perubahan kompatibilitas terpisah dengan lint, typecheck, build, dan regresi UI.
3. CI menggagalkan advisory production baru kategori critical melalui `npm audit --omit=dev --audit-level=critical`.
4. Advisory high/moderate tetap merupakan risiko terbuka; bukan dinyatakan selesai.

## Status eksekusi CI pertama

Belum dijalankan. Prasyarat yang belum terpenuhi:

- repository belum memiliki commit awal;
- remote Git belum ditentukan;
- URL/akses Jenkins controller belum tersedia;
- agent Linux berlabel `docker` belum diverifikasi;
- Docker Engine dan Compose v2 belum tersedia pada workspace Windows saat audit.

Pipeline hanya boleh dijalankan setelah review manusia atas commit awal dan verifikasi agent. Tidak ada deployment dalam pipeline ini.

## Validasi Fase 4B.2B

- PIN numerik tepat 6 digit ditegakkan pada frontend serta validator pre-check, submit, create, dan reset backend.
- Proxy selfie dibatasi middleware backend untuk Supervisor dan Superadmin; Operator tetap dapat membaca dashboard dan data attendance tanpa foto.
- PHPUnit dikonfigurasi `failOnSkipped="true"`; tes concurrency gagal tegas bila bukan MySQL 8.0/InnoDB atau ekstensi `pcntl` tidak tersedia.
- Verifikasi statis Compose: MySQL tanpa host port, DB/selfie memakai volume terpisah, backend Apache melayani HTTP port 80, dan health check backend memanggil `/up` melalui HTTP.

## Validasi Fase 4B.2C

Audit runtime lokal 11 Oktober 2026:

| Pemeriksaan | Lingkungan | Hasil faktual |
|---|---|---|
| Laravel/PHP | PHP `8.2.12` XAMPP, Laravel `12.69.3` | Tersedia. Composer `2.10.3` dijalankan sebagai PHAR sementara di luar repository. |
| PHP format | Pint `1.30.4` | Pemeriksaan awal gagal pada 11 file; Pint memperbaiki format. Pemeriksaan ulang lulus. |
| Database kosong dan migration | MariaDB `10.4.32`, InnoDB, schema khusus `absensi_sppg_test_4b2c` | Sepuluh migration lulus; seluruh tabel aplikasi terverifikasi InnoDB. Ini bukti kompatibilitas MariaDB lokal, bukan pengganti MySQL 8.0. |
| PHPUnit non-concurrency | MariaDB schema khusus | **20 lulus, 0 gagal, 0 error, 0 skipped; 120 assertions.** |
| Security suite | MariaDB schema khusus | **12 lulus, 0 gagal, 0 error, 0 skipped; 76 assertions.** Mencakup PIN, rate limit, auth/RBAC, audit rollback, cleanup selfie, dan media privat. |
| Seluruh PHPUnit | MariaDB/Windows | 19 lulus dan 2 gagal pada eksekusi awal: concurrency gagal karena `pcntl` tidak tersedia; cache media menemukan respons `public`. Cache media telah diperbaiki dan security suite kemudian lulus. |
| Concurrency | Windows PHP tanpa `pcntl`; MariaDB, bukan MySQL 8.0 | **Belum tervalidasi.** Tes dieksekusi dan gagal tegas pada prasyarat `pcntl`; tidak skipped. |
| Frontend | Node/npm Windows | `lint`, `typecheck`, dan production `build` lulus ulang. |
| Composer audit | `composer.lock` | 0 advisory, 0 abandoned package. |
| npm audit | lockfile aktual | Tetap 8 high + 3 moderate keseluruhan; 1 high + 1 moderate production. |
| Docker | Docker/Compose tidak tersedia; WSL tidak terpasang | Config runtime, image build, startup, dan health check belum dijalankan. |

Temuan runtime yang diperbaiki:

1. `BinaryFileResponse` menormalisasi header media menjadi `public` walau string `Cache-Control` diberikan langsung. Controller kini memakai `setPrivate()`, `setMaxAge(0)`, dan direktif `no-store`; regression test memeriksa ketiga semantik tanpa bergantung urutan serialisasi.
2. Format PHP distandarkan dengan Pint. Tidak ada perubahan logika pada file format-only.

Keputusan readiness Fase 4B.2C: **BLOCKED**. Syarat sebelum initial commit:

1. Jalankan migration dan seluruh PHPUnit pada MySQL 8.0 InnoDB di agent Linux terisolasi.
2. Jalankan `AttendanceConcurrencyTest` dengan `pcntl`; wajib lulus dan tidak skipped.
3. Jalankan `docker compose config`, build image, stack test, serta health checks pada Docker terisolasi.
4. Review manusia menerima risiko advisory npm atau menyetujui pekerjaan upgrade major terpisah.

## Batas cleanup CI

`docker compose down --volumes` hanya dijalankan terhadap project name unik `absensi-sppg-ci-${BUILD_NUMBER}` dengan database MySQL `tmpfs`. Perintah ini tidak boleh diarahkan ke project Compose lokal, staging, atau production.

## Validasi lokal Fase 4B.2D — XAMPP/Windows

Validasi aktual 11 Oktober 2026 menggunakan PHP XAMPP `8.2.12`, Laravel `12.69.3`, PHPUnit `11.5.57`, Pint `1.30.4`, MariaDB `10.4.32`, Node `24.15.0`, dan npm `12.0.2`. PHP menyediakan `pdo_mysql`, tetapi tidak menyediakan `pcntl`.

### LULUS LOKAL

| Pemeriksaan | Hasil aktual |
|---|---|
| Status awal Git | 132 staged; 0 unstaged; 0 untracked; pemeriksaan whitespace staged/unstaged lulus. |
| Pint | Lulus, exit `0`, termasuk pemeriksaan ulang setelah perbaikan privasi log. |
| Schema kosong dan migration | Schema khusus `absensi_sppg_test_4b2d_local` diverifikasi kosong. Sepuluh migration lulus, exit `0`; 11 tabel termasuk tabel migration terbentuk dan seluruhnya InnoDB. |
| Suite Windows-compatible final | **20 passed, 121 assertions, 0 failed, 0 errors, 0 skipped**, exit `0`. Tes concurrency saja dikecualikan karena `pcntl` tidak tersedia. |
| Security suite setelah regression fix | **12 passed, 77 assertions, 0 failed, 0 errors, 0 skipped**, exit `0`. |
| Aturan presensi | PIN tepat enam digit, rate limiting PIN, window check-in/check-out, shift malam dan `work_date`, jadwal hilang, overlap, retry idempoten, audit rollback, dan cleanup selfie lulus. |
| Privasi selfie | Operator ditolak; Supervisor dan Superadmin diizinkan; anonim ditolak; path privat tidak ada pada respons; media memiliki `private`, `no-store`, dan `max-age=0`; cleanup file saat transaksi gagal lulus. |
| Privasi log | Context error presensi diperbaiki agar hanya menyimpan kelas exception dan ID pegawai. Regression test memastikan context tidak memuat PIN, password, token, atau secret. |
| Frontend lokal | `npm run lint`, `npm run typecheck`, dan `npm run build` masing-masing lulus dengan exit `0`; Next.js `15.5.27`. |
| Composer audit | Tidak ditemukan security advisory; exit `0`. |
| Cleanup database uji | Seluruh schema/user khusus pengujian yang dibuat pada fase ini dihapus; verifikasi akhir schema = `0`, user = `0`. |

### GAGAL / RISIKO TERBUKA

- Tidak ada test kompatibel Windows/MariaDB yang gagal pada eksekusi final.
- Audit npm tetap mengembalikan exit `1`: keseluruhan **0 critical, 8 high, 3 moderate**; production **0 critical, 1 high, 1 moderate**. Tidak dilakukan `npm audit fix --force` atau upgrade major otomatis.
- Audit lokal menemukan log lama memuat PIN test akibat objek exception pernah dicatat bersama stack trace/argumen. Implementasi telah disanitasi dan regression test lulus. File log diabaikan Git dan tidak masuk staging; log lama lokal tidak dijadikan artifact atau laporan.

### BELUM TERUJI

1. Migration dan seluruh PHPUnit pada MySQL `8.0.x` aktual.
2. `AttendanceConcurrencyTest` pada Linux dengan `pcntl`, MySQL 8.0, dan InnoDB.
3. Verifikasi runtime bahwa concurrency menghasilkan tepat satu attendance dan satu selfie pada lingkungan tersebut.
4. `docker compose config`, build image backend/frontend, startup stack, migration container, dan health checks karena Docker tidak tersedia pada laptop.
5. Jenkins pipeline aktual.

MariaDB `10.4.32` bukan bukti kompatibilitas MySQL 8.0. Pengecualian `AttendanceConcurrencyTest` pada validasi lokal adalah batas runtime yang dinyatakan eksplisit, bukan status lulus.

### Blocker sebelum validasi VPS/runner dipertimbangkan

1. Persetujuan lingkungan Linux terisolasi yang tidak memakai Dockerhost operasional/thin pool Proxmox yang kritis.
2. MySQL 8.0 dan `pcntl` untuk full suite serta concurrency.
3. Docker Engine/Compose v2 terisolasi untuk build dan health check.
4. Keputusan manusia atas advisory npm high/moderate atau pekerjaan upgrade kompatibilitas terpisah.
5. Initial commit, remote resmi, dan CI tetap belum tersedia; tidak ada commit/push/deployment pada fase ini.
