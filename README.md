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
Letakkan file `provinces.json`, `regencies.json`, `districts.json`, dan `villages.json` di dalam folder `database/data/`.
Lalu jalankan perintah artisan di dalam kontainer:

```bash
docker compose exec app php artisan wilayah:import
```

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