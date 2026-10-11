# Strategi URL API

Dokumen ini menetapkan strategi `NEXT_PUBLIC_API_BASE_URL` sebelum perubahan konfigurasi Fase 4B.1.

## Keputusan

| Lingkungan | Nilai browser | Ketentuan |
|---|---|---|
| Development pada workstation | URL eksplisit, misalnya `http://localhost:8000/api/v1` | Hanya untuk browser pada workstation yang sama melalui `frontend/.env.local`; file tetap diabaikan Git. |
| Docker Compose lokal | Same-origin `/api/v1` | Proxy Compose menyajikan frontend dan API pada `APP_PORT`; default bind ke `127.0.0.1`. |
| Uji dari HP/perangkat lain | Same-origin `/api/v1` | Wajib memakai reverse proxy HTTPS yang menyajikan frontend dan meneruskan `/api/v1` ke backend. Hostname lokal belum ditentukan. |
| Staging | Same-origin `/api/v1` | Reverse proxy/ingress meneruskan `/api/v1` ke Laravel. Hostname staging belum ditentukan. |
| Production | Same-origin `/api/v1` | Reverse proxy/ingress meneruskan `/api/v1` ke Laravel. Hostname production belum ditentukan. |
| Image CI | Same-origin `/api/v1` | Image netral terhadap hostname; tidak menyematkan `localhost` atau domain yang belum disetujui. |

`localhost` selalu merujuk ke perangkat yang menjalankan browser. Karena itu, image untuk HP, staging, atau production tidak boleh dibangun dengan URL `localhost`.

## Perubahan Fase 4B.1

1. Fallback source frontend menjadi `/api/v1` dan dipusatkan pada modul API.
2. `PinPad` memakai helper verifikasi PIN yang sudah tersedia; tidak membuat URL sendiri.
3. URL media admin memakai helper base URL yang sama.
4. Default build `frontend/Dockerfile` dan build Jenkins menjadi `/api/v1`.
5. `infra/docker-compose.yml` menyediakan proxy same-origin; bind lokal tetap `127.0.0.1`, sedangkan deployment LAN dapat mengubah `APP_BIND_ADDRESS`.
6. Pipeline tidak menetapkan hostname staging atau production.

## Prasyarat reverse proxy

Reverse proxy lingkungan harus:

- melayani frontend dan API melalui origin HTTPS yang sama;
- meneruskan `/api/v1/*` ke backend Laravel tanpa menghapus prefix yang diperlukan;
- meneruskan header client yang relevan secara aman;
- tidak mengekspos MySQL;
- menetapkan batas ukuran request yang cukup untuk selfie maksimal 5 MB.

Konfigurasi reverse proxy, hostname, TLS, Cloudflare Tunnel, staging, dan production berada di luar Fase 4B.1.

## Media privat

Endpoint selfie admin tetap dilindungi Bearer token dan middleware RBAC backend. Hanya Supervisor dan Superadmin diizinkan; Operator menerima `403`. Frontend tidak memasang endpoint langsung pada `src` elemen `<img>`. Frontend melakukan `fetch` terautentikasi, membuat Blob URL lokal, lalu me-revoke URL ketika selection berubah atau modal ditutup. Respons proxy memakai `private, no-store` dan tidak mengekspos path private storage. Temporary signed URL tidak digunakan pada implementasi saat ini.
