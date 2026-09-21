# Master Wilayah GIS Server (Laravel 11 & MySQL 8)

Server master mandiri untuk mendistribusikan data batas wilayah kerja Puskesmas (Poligon & Koordinat).

## 1. Menjalankan Server dengan Docker
```bash
docker compose up -d --build
```

Setelah kontainer berjalan:
* **API Health Check:** `http://localhost:8080/api/health`
* **MySQL Host (External):** `localhost:33066`

## 2. Mengimpor Data Master GeoJSON
Dataset master wilayah Indonesia (38 Provinsi, 514 Kab/Kota, 7.285 Kecamatan, dan 83.762 Desa/Kelurahan) telah tersedia di direktori `database/data/` dalam format terkompresi `.json.gz`.

Jalankan perintah import di dalam kontainer:

```bash
docker compose exec app php artisan wilayah:import
```
*(Catatan: Perintah ini secara otomatis membaca file `.json.gz` dan menggabungkan seluruh part desa tanpa perlu diekstrak manual).*


## 3. Cek Status Rekap Data
```bash
docker compose exec app php artisan wilayah:status
```

## 4. Pengujian Endpoint Setup Puskesmas
Gunakan cURL atau Postman untuk menguji:

```bash
curl -X GET http://localhost:8080/api/v1/wilayah/setup-district/110101 \
  -H "Authorization: Bearer rahasia-token-wilayah-pusat-2026"
```