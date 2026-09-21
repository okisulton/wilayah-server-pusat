#!/bin/bash
set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE_DIR="${PROJECT_DIR}/deploy_package"
OUTPUT_TAR="${PROJECT_DIR}/wilayah-deploy-package.tar.gz"

echo "=========================================================="
echo "   EKSPOR DOCKER IMAGES & ARTIFAK WILAYAH SERVER PUSAT    "
echo "=========================================================="

# Default ke linux/amd64 karena server Linux x86_64 umumnya digunakan
PLATFORM="${DOCKER_PLATFORM:-linux/amd64}"
echo ">> Target Arsitektur: ${PLATFORM}"

# 1. Bersihkan package dir lama
rm -rf "${PACKAGE_DIR}" "${OUTPUT_TAR}"
mkdir -p "${PACKAGE_DIR}"

# 2. Build Docker image App (PHP-FPM)
echo ">> [1/5] Membangun image wilayah-app:prod (${PLATFORM})..."
docker build --platform "${PLATFORM}" -f "${PROJECT_DIR}/docker/php/Dockerfile.prod" -t wilayah-app:prod "${PROJECT_DIR}"

# 3. Build Docker image Web (Nginx)
echo ">> [2/5] Membangun image wilayah-nginx:prod (${PLATFORM})..."
docker build --platform "${PLATFORM}" -f "${PROJECT_DIR}/docker/nginx/Dockerfile.prod" -t wilayah-nginx:prod "${PROJECT_DIR}"

# 4. Pull MySQL 8 untuk target platform jika belum ada
echo ">> [3/5] Memastikan image mysql:8.0.36 (${PLATFORM}) tersedia..."
docker pull --platform "${PLATFORM}" mysql:8.0.36

# 5. Export MySQL Database dari container lokal yang sedang berjalan
echo ">> [4/5] Melakukan dump database master (83.762 desa, 7.285 kecamatan)..."
if docker ps | grep -q "wilayah_mysql"; then
    docker compose exec -T db mysqldump -u wilayah_user -pwilayah_secret db_wilayah_pusat | gzip > "${PACKAGE_DIR}/db_wilayah_pusat.sql.gz"
    echo "✓ Database berhasil di-dump ke ${PACKAGE_DIR}/db_wilayah_pusat.sql.gz"
else
    echo ">> [PERINGATAN] Container wilayah_mysql tidak aktif. Melewati dump database."
fi

# 6. Simpan images ke tar file terkompresi (hanya image custom app & nginx agar ringan dan bebas konflik arsitektur)
echo ">> [5/5] Menyimpan Docker images ke file tar (wilayah-images.tar.gz)..."
docker save wilayah-app:prod wilayah-nginx:prod | gzip > "${PACKAGE_DIR}/wilayah-images.tar.gz"

# 7. Salin file konfigurasi ke folder package
cp "${PROJECT_DIR}/docker-compose.prod.yml" "${PACKAGE_DIR}/docker-compose.prod.yml"
cp "${PROJECT_DIR}/.env.production" "${PACKAGE_DIR}/.env.production"
cp "${PROJECT_DIR}/docker/mysql/my.cnf" "${PACKAGE_DIR}/my.cnf"
cp "${PROJECT_DIR}/scripts/run-on-server.sh" "${PACKAGE_DIR}/run-on-server.sh"
chmod +x "${PACKAGE_DIR}/run-on-server.sh"

# 8. Bungkus seluruh deploy_package menjadi 1 file tar.gz
echo ">> Mengemas seluruh paket deploy menjadi wilayah-deploy-package.tar.gz..."
tar -czf "${OUTPUT_TAR}" -C "${PROJECT_DIR}" deploy_package

echo "=========================================================="
echo "✓ EKSPOR SELESAI!"
echo ""
echo "File paket deploy telah siap: ${OUTPUT_TAR}"
echo ""
echo "Langkah selanjutnya:"
echo "1. Salin file ke server:"
echo "   scp ${OUTPUT_TAR} kinarya@serverlab:~/"
echo ""
echo "2. Masuk ke server dan jalankan:"
echo "   ssh kinarya@serverlab"
echo "   tar -xzf wilayah-deploy-package.tar.gz"
echo "   cd deploy_package"
echo "   bash run-on-server.sh"
echo "=========================================================="
