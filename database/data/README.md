# Direktori Dataset Master GeoJSON Wilayah Indonesia

Direktori ini berisi dataset spasial batas wilayah administratif seluruh Indonesia (standar Kemendagri & BPS) yang telah dikompresi ke format `.json.gz` agar aman disimpan dalam repositori Git tanpa melanggar batas ukuran file GitHub (< 100 MB).

---

## 🗂️ Daftar File Dataset

| File | Cakupan Wilayah | Jumlah Data | Ukuran Terkompresi | Format Poligon |
|---|---|---|---|---|
| `provinces.json.gz` | Provinsi se-Indonesia | **38** Provinsi | ~1.6 MB | MultiPolygon (SRID 4326) |
| `regencies.json.gz` | Kabupaten & Kota | **514** Kab/Kota | ~2.3 MB | MultiPolygon (SRID 4326) |
| `districts.json.gz` | Kecamatan | **7.285** Kecamatan | ~4.1 MB | MultiPolygon (SRID 4326) |
| `villages_part1.json.gz` | Desa & Kelurahan (Part 1) | ~20.941 Desa | ~16 MB | MultiPolygon (SRID 4326) |
| `villages_part2.json.gz` | Desa & Kelurahan (Part 2) | ~20.941 Desa | ~22 MB | MultiPolygon (SRID 4326) |
| `villages_part3.json.gz` | Desa & Kelurahan (Part 3) | ~20.941 Desa | ~23 MB | MultiPolygon (SRID 4326) |
| `villages_part4.json.gz` | Desa & Kelurahan (Part 4) | ~20.939 Desa | ~31 MB | MultiPolygon (SRID 4326) |
| **TOTAL** | **Seluruh Wilayah Indonesia** | **83.762** Desa | **~100 MB** | **WGS 84 / SRID 4326** |

> [!NOTE]
> * **Mengapa desa dibagi 4 part?** File asli `villages.json` berukuran 346 MB, melebihi batas upload GitHub (100 MB). Dengan pembagian 4 part terkompresi, ukuran tiap file berkisar antara 16–31 MB (jauh di bawah batas rekomendasi GitHub 50 MB).
> * **Zero Decompression Effort:** Anda **tidak perlu mengekstrak** file `.json.gz` secara manual. Perintah import Laravel otomatis mendekompresi dan menggabungkan seluruh part langsung di dalam memori.

---

## 🚀 Cara Import ke Database Master

### 1. Jalankan Perintah Import
Jalankan perintah berikut di terminal (jika menggunakan Docker):

```bash
docker compose exec app php artisan wilayah:import
```

Atau jika dijalankan di PHP lokal langsung:
```bash
php artisan wilayah:import
```

### 2. Verifikasi Hasil Import
Periksa rekapitulasi data yang tersimpan di MySQL:

```bash
docker compose exec app php artisan wilayah:status
```

Hasil yang diharapkan:
```text
========================================
   STATUS DATABASE MASTER WILAYAH GIS   
========================================
1. Total Provinsi           : 38
2. Total Kabupaten / Kota   : 514
3. Total Kecamatan          : 7285
4. Total Desa / Kelurahan   : 83762
========================================
```

---

## ⚙️ Spesifikasi & Standar Teknis

* **Datum / SRS:** WGS 84 (EPSG:4326).
* **Format Koordinat Poligon:** GeoJSON standar `[Longitude, Latitude]`.
* **Database Engine:** MySQL 8.0+ dengan spatial engine InnoDB (`MULTIPOLYGON SRID 4326`).
* **Optimasi Import:** Dilengkapi *chunk transaction* (200 baris per batch), *in-memory lookup* pemetaan ID hierarki, dan normalisasi poligon otomatis via `GeoJsonHelper`.
