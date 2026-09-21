#!/bin/bash
set -e

echo "=============================================="
echo "   DEPLOYMENT WILAYAH SERVER PUSAT KE SERVER  "
echo "=============================================="

# 1. Pastikan file .env tersedia
if [ ! -f .env ]; then
    echo ">> Membuat file .env dari .env.production..."
    cp .env.production .env
fi

# Load variabel dari .env jika ada
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

DB_USER="${DB_USERNAME:-wilayah_user}"
DB_PASS="${DB_PASSWORD:-wilayah_secret_prod_2026}"
DB_NAME="${DB_DATABASE:-db_wilayah_pusat}"
HTTP_PORT_VAL="${HTTP_PORT:-8088}"

# 2. Load Docker Images
echo ">> Memuat Docker images dari file tar..."
if [ -f wilayah-images.tar.gz ]; then
    echo ">> Mengekstrak dan me-load image Docker (ini membutuhkan waktu sebentar)..."
    gunzip -c wilayah-images.tar.gz | docker load
elif [ -f wilayah-images.tar ]; then
    docker load -i wilayah-images.tar
else
    echo ">> [INFO] File tar images tidak ditemukan, docker compose akan mencoba menggunakan image lokal/pull."
fi

# 3. Pastikan image MySQL native server tersedia
echo ">> Menyiapkan image MySQL 8 (native arsitektur server)..."
docker pull mysql:8.0.36

# 4. Jalankan container
echo ">> Menjalankan container dengan docker-compose.prod.yml..."
docker compose -f docker-compose.prod.yml up -d

# 4. Tunggu MySQL sehat
echo ">> Menunggu MySQL siap (healthy)..."
until [ "$(docker inspect -f '{{.State.Health.Status}}' wilayah_mysql_prod 2>/dev/null)" = "healthy" ]; do
    printf '.'
    sleep 2
done
echo " Siap!"

# 5. Restore database jika file dump tersedia dan database masih kosong
if [ -f db_wilayah_pusat.sql.gz ]; then
    TABLE_COUNT=$(docker compose -f docker-compose.prod.yml exec -T db mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT count(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null || echo 0)
    if [ "$TABLE_COUNT" -eq 0 ]; then
        echo ">> Database kosong. Mengimpor 83.762 data master wilayah dari db_wilayah_pusat.sql.gz..."
        gunzip -c db_wilayah_pusat.sql.gz | docker compose -f docker-compose.prod.yml exec -T db mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
        echo "✓ Database master berhasil di-restore!"
    else
        echo ">> Database sudah memiliki $TABLE_COUNT tabel. Melewati restore dump."
    fi
fi

# 6. Optimize Laravel
echo ">> Mengoptimalkan cache Laravel..."
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache

echo "=============================================="
echo "✓ DEPLOYMENT SELESAI!"
echo "Aplikasi berjalan di port ${HTTP_PORT_VAL}."
echo ""
echo "Uji kesehatan API:"
echo "curl http://localhost:${HTTP_PORT_VAL}/api/health"
echo "=============================================="
