# 📘 Panduan Lengkap Deployment Wilayah Server Pusat

Dokumen ini adalah panduan komprehensif mulai dari **pembuatan paket di komputer lokal (Mac)**, **proses upload ke server**, hingga **eksekusi instalasi dan verifikasi di server produksi (`45.127.134.12`)**.

---

## 🏗️ 1. Informasi Arsitektur & Lingkungan

* **Fungsi Aplikasi:** Server Pusat Data Master Wilayah Indonesia (GeoJSON MultiPolygon WGS 84).
* **Data Master yang Dikelola:**
  * 38 Provinsi
  * 514 Kabupaten / Kota
  * 7.285 Kecamatan
  * 83.762 Desa / Kelurahan
* **Server Target:**
  * **IP Server:** `45.127.134.12`
  * **Username:** `kinarya`
  * **Direktori Aplikasi di Server:** `~/webapp/IndonesaGeoJson`
  * **Port HTTP Nginx:** **`8088`** *(Dipastikan kosong dan aman dari port yang sudah berjalan di server)*
  * **Port MySQL:** **`33066`** *(Di-bind ke `127.0.0.1` internal server demi keamanan)*

---

## 💻 2. FASE 1: Pembuatan Paket di Komputer Lokal (Mac)

Tahap ini dilakukan di laptop lokal Anda sebelum mengunggah file ke server.

### Langkah 1.1: Pastikan Database Lokal Sudah Terisi
Pastikan seluruh 83.762 data desa sudah masuk ke MySQL lokal:
```bash
docker compose exec db mysql -u wilayah_user -pwilayah_secret db_wilayah_pusat -e "
SELECT 'provinces' as tbl, count(*) as count FROM provinces
UNION ALL SELECT 'regencies', count(*) FROM regencies
UNION ALL SELECT 'districts', count(*) FROM districts
UNION ALL SELECT 'villages', count(*) FROM villages;
"
```
*(Pastikan jumlah desa adalah 83.762)*.

---

### Langkah 1.2: Jalankan Script Ekspor
Di terminal Mac Anda pada direktori project:
```bash
./scripts/export-images.sh
```

**Apa yang dilakukan script ini?**
1. Membangun image Docker `wilayah-app:prod` dan `wilayah-nginx:prod` dengan arsitektur **`linux/amd64`** (sehingga 100% kompatibel dengan server Linux).
2. Melakukan dump database MySQL lokal ke file `db_wilayah_pusat.sql.gz`.
3. Menyimpan image custom aplikasi ke `wilayah-images.tar.gz`.
4. Mengemas seluruh konfigurasi produksi ke file tunggal: **`wilayah-deploy-package.tar.gz`**.

---

## 🚀 3. FASE 2: Unggah Paket ke Server

Jalankan perintah `scp` berikut dari terminal lokal Anda untuk mengirim file arsip ke server:

```bash
scp wilayah-deploy-package.tar.gz kinarya@45.127.134.12:~/webapp/IndonesaGeoJson/
```

> **Tips:**
> Jika direktori `~/webapp/IndonesaGeoJson` belum ada di server, buat terlebih dahulu:
> `ssh kinarya@45.127.134.12 "mkdir -p ~/webapp/IndonesaGeoJson"`

---

## 🖥️ 4. FASE 3: Eksekusi & Instalasi di Server Target

Buka terminal SSH ke server:

```bash
ssh kinarya@45.127.134.12
```

### Langkah 3.1: Masuk ke Direktori dan Ekstrak Paket
```bash
cd ~/webapp/IndonesaGeoJson
tar -xzf wilayah-deploy-package.tar.gz
cd deploy_package
```

Pastikan isi folder lengkap:
```bash
ls -la
```
*File yang harus ada:*
* `.env.production`
* `docker-compose.prod.yml`
* `my.cnf`
* `db_wilayah_pusat.sql.gz`
* `wilayah-images.tar.gz`
* `run-on-server.sh`

---

### Langkah 3.2: Jalankan Script Instalasi Otomatis
```bash
bash run-on-server.sh
```

**Alur Kerja Script Otomatis:**
1. Menyalin `.env.production` menjadi `.env`.
2. Me-load Docker image `wilayah-app:prod` dan `wilayah-nginx:prod`.
3. Mengunduh image resmi `mysql:8.0.36` versi arsitektur native Linux server.
4. Menyalakan container Docker di background (`docker compose up -d`).
5. Menunggu MySQL siap (*healthy*).
6. Mengimpor database master (83.762 wilayah) secara otomatis dari `db_wilayah_pusat.sql.gz`.
7. Mengoptimalkan cache Laravel (`config:cache`, `route:cache`).

---

## 🧪 5. FASE 4: Pengujian & Verifikasi API

Setelah instalasi selesai, lakukan pengujian untuk memastikan layanan berjalan normal:

### 1. Uji Health Check API (Koneksi Database)
* **Dari dalam server:**
  ```bash
  curl http://localhost:8088/api/health
  ```
* **Dari browser / laptop Anda:**
  ```bash
  curl http://45.127.134.12:8088/api/health
  ```
* **Response Sukses:**
  ```json
  {"status":"ok","timestamp":"...","database":"connected"}
  ```

---

### 2. Uji Ambil Data Wilayah untuk Sistem Puskesmas
Kirim request dengan menyertakan Bearer Token:
```bash
curl -H "Authorization: Bearer rahasia-token-wilayah-pusat-2026" \
     http://45.127.134.12:8088/api/v1/wilayah/setup-district/110101
```

* **Hasil:**
  Mengembalikan data 1 paket lengkap:
  * 1 Provinsi (beserta poligon GeoJSON WGS 84)
  * 1 Kabupaten/Kota (beserta poligon GeoJSON)
  * 1 Kecamatan kerja puskesmas (beserta poligon GeoJSON)
  * Seluruh Desa/Kelurahan binaan di kecamatan tersebut (beserta poligon GeoJSON masing-masing)

---

## ⚠️ 6. FASE 5: Pemecahan Masalah (Troubleshooting)

### Kasus: MySQL Berstatus `unhealthy` karena Ketidakcocokan Arsitektur Image
Jika muncul pesan:
`The requested image's platform (linux/arm64) does not match the detected host platform (linux/amd64)`

**Solusi Cepat di Server:**
Jalankan langkah-langkah berikut langsung di folder `deploy_package/`:
```bash
# 1. Hentikan container dan bersihkan volume gagal
docker compose -f docker-compose.prod.yml down -v

# 2. Unduh image MySQL versi native arsitektur server Linux
docker pull mysql:8.0.36

# 3. Hapus file tar images lama agar tidak menimpa ulang
rm -f wilayah-images.tar.gz

# 4. Jalankan kembali script deployment
bash run-on-server.sh
```

---

## 🛠️ 7. FASE 6: Perintah Pemeliharaan (Maintenance)

Semua perintah dijalankan di dalam folder `~/webapp/IndonesaGeoJson/deploy_package/` di server:

* **Melihat Status Container:**
  ```bash
  docker compose -f docker-compose.prod.yml ps
  ```

* **Melihat Log Aplikasi Laravel:**
  ```bash
  docker compose -f docker-compose.prod.yml logs -f app
  ```

* **Melihat Log Web Server Nginx:**
  ```bash
  docker compose -f docker-compose.prod.yml logs -f web
  ```

* **Melihat Log Database MySQL:**
  ```bash
  docker compose -f docker-compose.prod.yml logs -f db
  ```

* **Merestart Layanan:**
  ```bash
  docker compose -f docker-compose.prod.yml restart
  ```

* **Menghentikan Layanan:**
  ```bash
  docker compose -f docker-compose.prod.yml down
  ```

* **Menyalakan Kembali Layanan:**
  ```bash
  docker compose -f docker-compose.prod.yml up -d
  ```

---

## 🔐 8. Mengubah Token API Puskesmas

Jika ingin memperbarui token keamanan API:
1. Buka file `.env` di server:
   ```bash
   nano .env
   ```
2. Ubah nilai token:
   ```ini
   MASTER_API_TOKEN=masukkan-token-baru-yang-panjang-dan-aman
   ```
3. Perbarui cache konfigurasi Laravel:
   ```bash
   docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
   ```
