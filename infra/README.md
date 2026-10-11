# Infrastruktur Lokal dan CI

Konfigurasi ini mendukung deployment Jenkins. Proxy same-origin dipublikasikan pada `APP_PORT`; MySQL tidak dipublikasikan ke host.

## Prasyarat lokal

1. Docker Engine dengan Compose v2.
2. Buat `infra/.env` dari contoh berikut. Jangan commit file tersebut.

```dotenv
APP_VERSION=local
VCS_REF=working-tree
APP_ENV=local
APP_DEBUG=false
APP_KEY=base64:GANTI_DENGAN_HASIL_PHP_ARTISAN_KEY_GENERATE_SHOW
APP_URL=http://localhost:8040
PUBLIC_API_BASE_URL=/api/v1
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=3040
FRONTEND_PORT=3041
BACKEND_PORT=8040
DB_DATABASE=absensi_sppg
DB_USERNAME=sppg_user
DB_PASSWORD=GANTI_PASSWORD_APLIKASI
DB_ROOT_PASSWORD=GANTI_PASSWORD_ROOT
```

## Menjalankan stack lokal

```bash
docker compose --env-file infra/.env -f infra/docker-compose.yml config
docker compose --env-file infra/.env -f infra/docker-compose.yml up -d --build
docker compose --env-file infra/.env -f infra/docker-compose.yml ps
curl --fail http://127.0.0.1:8040/up
curl --fail http://127.0.0.1:3040/
curl --fail http://127.0.0.1:3040/api/v1/public/employees
```

Migrasi non-destruktif:

```bash
docker compose --env-file infra/.env -f infra/docker-compose.yml exec backend php artisan migrate --force
```

> Jangan memakai `migrate:fresh`, `docker compose down --volumes`, atau `docker system prune` pada database yang harus dipertahankan.

Volume persisten:
- `absensi-sppg_db_data`: data MySQL.
- `absensi-sppg_private_selfies`: selfie privat.

Container backend memperbaiki ownership `storage` menjadi `www-data` saat start. Health check backend meminta `/up`; frontend meminta `/`; MySQL memakai `mysqladmin ping`.

## Test backend terisolasi

```bash
export COMPOSE_PROJECT_NAME=absensi-sppg-ci-local
export BACKEND_TEST_IMAGE=absensi-sppg-backend-testing:local
docker build --target testing -t "$BACKEND_TEST_IMAGE" backend
docker compose -f infra/docker-compose.test.yml up --abort-on-container-exit --exit-code-from test_runner --no-build
docker compose -f infra/docker-compose.test.yml down --volumes --remove-orphans
```

MySQL test memakai `tmpfs`, tanpa port host, tanpa kredensial production. `phpunit.xml` tidak mengunci host atau kredensial; Compose menyuntikkan seluruh konfigurasi DB. Laporan JUnit ditulis ke `backend/storage/test-results/junit.xml`.

## Backup sebelum staging

```bash
docker compose --env-file infra/.env -f infra/docker-compose.yml exec -T db \
  sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql

docker run --rm -v absensi-sppg_private_selfies:/data -v "$PWD":/backup \
  alpine tar czf /backup/selfies-backup.tar.gz -C /data .
```

Uji restore wajib dilakukan pada lingkungan terpisah sebelum staging.

## Jenkins

`Jenkinsfile` checkout, format/lint, test, build, validasi image, lalu deploy memakai `$JENKINS_HOME/absensi-sppg-deploy`. Secret dibuat sekali pada `.env` mode `0600`; redeploy memakai volume database/selfie yang sama. Cloudflare Tunnel tidak diubah.

## URL API browser

- Compose menyajikan frontend dan API melalui proxy same-origin dengan `PUBLIC_API_BASE_URL=/api/v1`.
- Lokal memakai `APP_BIND_ADDRESS=127.0.0.1`; Jenkins memakai `0.0.0.0` agar port aplikasi dapat diakses dari LAN.
- Akses publik tetap memerlukan reverse proxy HTTPS atau Cloudflare Tunnel terpisah.
- Hostname staging/production belum ditetapkan.
