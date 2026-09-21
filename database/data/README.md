# Direktori Dataset GeoJSON Wilayah Indonesia

Direktori ini berisi seluruh dataset master wilayah format GeoJSON yang telah dikompresi agar dapat disimpan di dalam Git:

- `provinces.json.gz` (38 Provinsi - 1.6 MB)
- `regencies.json.gz` (514 Kabupaten / Kota - 2.3 MB)
- `districts.json.gz` (7.285 Kecamatan - 4.1 MB)
- `villages_part1.json.gz` s/d `villages_part4.json.gz` (83.762 Desa / Kelurahan - total 92 MB terkompresi, dibagi 4 part agar ukuran masing-masing file < 35 MB dan memenuhi standar GitHub)

> **Catatan:**
> File mentah teks `*.json` uncompressed (terutama `villages.json` sebesar 346 MB) diabaikan oleh `.gitignore` untuk mencegah batas ukuran file 100 MB GitHub.
>
> Perintah import Laravel (`php artisan wilayah:import`) secara otomatis mendukung pembacaan file `.json.gz` dan penggabungan seluruh part desa tanpa perlu diekstrak manual.

### Cara Menjalankan Import:
```bash
docker compose exec app php artisan wilayah:import
```
