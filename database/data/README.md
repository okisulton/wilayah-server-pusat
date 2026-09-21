# Direktori Dataset GeoJSON Wilayah Indonesia

Direktori ini digunakan untuk menyimpan file dataset master wilayah format JSON dari Kemendagri / BPS:

- `provinces.json` (38 Provinsi)
- `regencies.json` (514 Kabupaten / Kota)
- `districts.json` (7.285 Kecamatan)
- `villages.json` (83.762 Desa / Kelurahan - ~346 MB)

> **Catatan:**
> Seluruh file dataset `*.json` diabaikan dari repositori Git (`.gitignore`) karena file `villages.json` berukuran 346 MB (melebihi batas maksimal unggahan GitHub 100 MB).
> 
> Untuk memuat data ke dalam database:
> 1. Tempatkan file JSON di dalam folder ini.
> 2. Jalankan perintah import di dalam container:
>    ```bash
>    docker compose exec app php artisan wilayah:import
>    ```
