# Analisis Mendalam: `wilayah-server-pusat`

> Dokumen ini dibuat dari hasil deep scan seluruh codebase.
> Tujuan: Memahami struktur, data, dan cara integrasi project ini ke aplikasi Sistem Puskesmas.

---

## 📌 Ringkasan Eksekutif

**`wilayah-server-pusat`** adalah **microservice GIS mandiri** berbasis Laravel 11 + MySQL 8 Spatial yang berfungsi sebagai *master server* untuk mendistribusikan data batas wilayah administratif Indonesia dalam format **GeoJSON/Spatial** (MultiPolygon SRID 4326).

| Atribut | Detail |
|---|---|
| Framework | Laravel 11 (PHP 8.3) |
| Database | MySQL 8.0.36 (Spatial Engine) |
| Infrastruktur | Docker (PHP-FPM + Nginx + MySQL) |
| Data Coverage | 38 Provinsi · 514 Kab/Kota · 7.285 Kecamatan · 83.762 Desa/Kelurahan |
| Format Data | GeoJSON MultiPolygon (SRID: 4326 / WGS84) |
| Proyeksi Koordinat | WGS84 (latitude/longitude desimal) |

---

## 🗂️ Struktur Direktori

```
wilayah-server-pusat/
├── app/
│   ├── Console/Commands/
│   │   └── ImportWilayahCommand.php    ← Perintah import data JSON ke DB
│   ├── Helpers/
│   │   └── GeoJsonHelper.php           ← Normalisasi koordinat → MultiPolygon
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── WilayahApiController.php  ← Endpoint API
│   │   └── Middleware/
│   │       └── VerifyPuskesmasToken.php  ← Auth token middleware
│   └── Models/
│       ├── Province.php
│       ├── Regency.php
│       ├── District.php
│       └── Village.php
├── database/
│   ├── data/                           ← File JSON sumber data (~368 MB total)
│   │   ├── provinces.json   (4.4 MB)   ← 38 Provinsi
│   │   ├── regencies.json   (6.3 MB)   ← 514 Kabupaten/Kota
│   │   ├── districts.json   (12.0 MB)  ← 7.285 Kecamatan
│   │   └── villages.json    (345.7 MB) ← 83.762 Desa/Kelurahan
│   └── migrations/
│       └── 2026_01_01_000001_create_wilayah_tables.php
├── docker/
│   ├── php/    Dockerfile + entrypoint.sh
│   ├── nginx/  default.conf
│   └── mysql/  my.cnf (spatial tuning)
├── routes/
│   ├── api.php      ← Definisi endpoint REST
│   └── console.php  ← Command wilayah:status
└── docker-compose.yml
```

---

## 🗄️ Skema Database

### Hierarki Tabel

```
provinces (38)
  └── regencies (514)       [province_id → provinces.id]
        └── districts (7.285)     [regency_id → regencies.id]
              └── villages (83.762)    [district_id → districts.id]
```

### Detail Kolom

#### Tabel `provinces`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | Auto increment |
| `code` | char(2) UNIQUE | Kode BPS 2-digit (misal: `11`, `32`) |
| `name` | varchar | Nama provinsi |
| `latitude` | decimal(10,7) nullable | Titik pusat |
| `longitude` | decimal(10,7) nullable | Titik pusat |
| `area` | geometry multipolygon SRID 4326 nullable | Polygon batas (**selalu NULL**, lihat catatan) |

#### Tabel `regencies`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | Auto increment |
| `province_id` | FK provinces.id | Cascade delete |
| `code` | char(4) UNIQUE | Kode BPS 4-digit (misal: `1101`) |
| `name` | varchar | Nama kabupaten/kota |
| `latitude` / `longitude` | decimal(10,7) | Titik pusat |
| `area` | geometry multipolygon SRID 4326 | Polygon batas + SPATIAL INDEX |

#### Tabel `districts`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | Auto increment |
| `regency_id` | FK regencies.id | Cascade delete |
| `code` | char(7) UNIQUE | Kode BPS 7-digit (misal: `1101011`) |
| `name` | varchar | Nama kecamatan |
| `latitude` / `longitude` | decimal(10,7) | Titik pusat |
| `area` | geometry multipolygon SRID 4326 | Polygon batas + SPATIAL INDEX |

#### Tabel `villages`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | Auto increment |
| `district_id` | FK districts.id | Cascade delete |
| `code` | char(10) UNIQUE | Kode BPS 10-digit |
| `name` | varchar | Nama desa/kelurahan |
| `latitude` / `longitude` | decimal(10,7) | Titik pusat |
| `area` | geometry multipolygon SRID 4326 | Polygon batas + SPATIAL INDEX |

---

## 📦 Format Data Sumber JSON

Setiap file JSON adalah array flat dari objek. Contoh struktur:

```json
// provinces.json — kunci: id (2 digit string)
{ "id": "11", "name": "Aceh", "latitude": 4.2257, "longitude": 96.9118, "coordinates": "[[[[lat,lon],...]]]" }

// regencies.json — kunci: id (4 digit), province_id (2 digit)
{ "id": "1101", "province_id": "11", "name": "Kabupaten Aceh Selatan", "latitude": 3.1618, "longitude": 97.4365, "coordinates": "[[[[lat,lon],...]]]" }

// districts.json — kunci: id (7 digit), province_id, regency_id
{ "id": "1101011", "province_id": "11", "regency_id": "1101", "name": "Bakongan", "latitude": ..., "longitude": ..., "coordinates": "[[[[lat,lon],...]]]" }

// villages.json — kunci: id (10 digit), province_id, regency_id, district_id
{ "id": "1101011001", "province_id": "11", "regency_id": "1101", "district_id": "1101011", "name": "...", "latitude": ..., "longitude": ..., "coordinates": "[[[[lat,lon],...]]]" }
```

> **PENTING — Urutan Koordinat**: Data JSON menyimpan koordinat sebagai `[latitude, longitude]`,
> bukan standar GeoJSON `[longitude, latitude]`. `GeoJsonHelper` secara otomatis membalik urutan ini
> saat proses import ke database.

---

## 🗺️ Daftar Provinsi & Jumlah Kabupaten/Kota

### Sumatera
| ID | Provinsi | Kab/Kota |
|---|---|---|
| 11 | Aceh | 23 |
| 12 | Sumatera Utara | 33 |
| 13 | Sumatera Barat | 19 |
| 14 | Riau | 12 |
| 15 | Jambi | 11 |
| 16 | Sumatera Selatan | 17 |
| 17 | Bengkulu | 10 |
| 18 | Lampung | 15 |
| 19 | Kepulauan Bangka Belitung | 7 |
| 21 | Kepulauan Riau | 7 |

### Jawa & Bali
| ID | Provinsi | Kab/Kota |
|---|---|---|
| 31 | DKI Jakarta | 6 |
| 32 | Jawa Barat | 27 |
| 33 | Jawa Tengah | 35 |
| 34 | DI Yogyakarta | 5 |
| 35 | Jawa Timur | 38 |
| 36 | Banten | 8 |
| 51 | Bali | 9 |

### Nusa Tenggara
| ID | Provinsi | Kab/Kota |
|---|---|---|
| 52 | Nusa Tenggara Barat | 10 |
| 53 | Nusa Tenggara Timur | 22 |

### Kalimantan
| ID | Provinsi | Kab/Kota |
|---|---|---|
| 61 | Kalimantan Barat | 14 |
| 62 | Kalimantan Tengah | 14 |
| 63 | Kalimantan Selatan | 13 |
| 64 | Kalimantan Timur | 10 |
| 65 | Kalimantan Utara | 5 |

### Sulawesi
| ID | Provinsi | Kab/Kota |
|---|---|---|
| 71 | Sulawesi Utara | 15 |
| 72 | Sulawesi Tengah | 13 |
| 73 | Sulawesi Selatan | 24 |
| 74 | Sulawesi Tenggara | 17 |
| 75 | Gorontalo | 6 |
| 76 | Sulawesi Barat | 6 |

### Maluku & Papua
| ID | Provinsi | Kab/Kota |
|---|---|---|
| 81 | Maluku | 11 |
| 82 | Maluku Utara | 10 |
| 91 | Papua | 9 |
| 92 | Papua Barat | 7 |
| 93 | Papua Selatan | 4 |
| 94 | Papua Tengah | 8 |
| 95 | Papua Pegunungan | 8 |
| 96 | Papua Barat Daya | 6 |

---

## 🌐 API Endpoints

### Autentikasi
Semua endpoint `v1/wilayah/` dilindungi token Bearer (dari env `MASTER_API_TOKEN`):
```
Authorization: Bearer rahasia-token-wilayah-pusat-2026
# atau
X-API-KEY: rahasia-token-wilayah-pusat-2026
```

---

### `GET /api/health` — Health Check (tanpa token)
**Response:**
```json
{ "status": "ok", "timestamp": "2026-09-21T10:00:00+07:00", "database": "connected" }
```

---

### `GET /api/v1/wilayah/setup-district/{code}` — Setup Data Kecamatan
Parameter `code`: kode BPS kecamatan **7 digit** (misal: `1101011`)

**Response sukses (200):**
```json
{
  "status": "success",
  "data": {
    "province": { "id": 1, "code": "11", "name": "Aceh", "latitude": 4.2257, "longitude": 96.9118 },
    "regency":  { "id": 1, "code": "1101", "name": "Kabupaten Aceh Selatan", "latitude": ..., "longitude": ..., "geojson": "{MultiPolygon GeoJSON}" },
    "district": { "id": 1, "code": "1101011", "name": "Bakongan", "latitude": ..., "longitude": ..., "geojson": "{MultiPolygon GeoJSON}" },
    "villages": [
      { "id": 1, "code": "1101011001", "name": "...", "latitude": ..., "longitude": ..., "geojson": "{MultiPolygon GeoJSON}" }
    ]
  }
}
```

**Response error (404):**
```json
{ "status": "error", "message": "Kode kecamatan '999999' tidak ditemukan di database master." }
```

**Fitur:**
- Response di-**cache file selama 30 hari** — sangat cepat untuk request berulang
- GeoJSON dikembalikan via `ST_AsGeoJSON(area)` — format standar GeoJSON siap pakai
- Polygon provinsi tidak dikembalikan (hanya titik pusat lat/lon)

---

## ⚙️ Artisan Commands

### `php artisan wilayah:import [--path=database/data]`
Import seluruh data JSON ke database spatial MySQL.

**Urutan import:** `provinces → regencies → districts → villages`

**Karakteristik:**
- Memory limit: 2048MB, time limit: unlimited
- `updateOrInsert` — aman dijalankan ulang (idempotent)
- Import desa: batch 200 record + DB transaction per chunk + progress bar
- Lookup relasi via in-memory array (performant)

### `php artisan wilayah:status`
Menampilkan rekap jumlah data di setiap tabel.

---

## 🐳 Infrastruktur Docker

| Service | Image | Port Host | Keterangan |
|---|---|---|---|
| `app` (wilayah_app) | php:8.3-fpm-alpine | — | Laravel + PHP-FPM |
| `web` (wilayah_nginx) | nginx:1.25-alpine | **8080**:80 | Reverse proxy |
| `db` (wilayah_mysql) | mysql:8.0.36 | **33066**:3306 | Database spatial |

**Auto-startup (entrypoint.sh):**
1. `composer install` (jika `vendor/` belum ada)
2. Copy `.env.example` → `.env` + generate app key (jika belum ada)
3. `php artisan migrate --force`
4. Start `php-fpm`

**MySQL Tuning Spatial (my.cnf):**
```ini
max_allowed_packet    = 256M   # wajib untuk polygon kompleks
innodb_buffer_pool_size = 512M
character-set-server  = utf8mb4
```

---

## 🔧 Environment Variables

| Variable | Default | Keterangan |
|---|---|---|
| `APP_KEY` | _(generate)_ | Wajib ada, auto-generate saat startup |
| `APP_TIMEZONE` | Asia/Jakarta | |
| `DB_DATABASE` | db_wilayah_pusat | |
| `DB_USERNAME` | wilayah_user | |
| `DB_PASSWORD` | wilayah_secret | |
| `CACHE_STORE` | file | Cache API response 30 hari |
| `MASTER_API_TOKEN` | rahasia-token-wilayah-pusat-2026 | **WAJIB ganti di production!** |

---

## 🔗 Cara Integrasi ke Aplikasi Puskesmas

Karena satu puskesmas melayani satu kecamatan, cukup **satu kali request** saat setup:

```bash
curl -X GET "http://localhost:8080/api/v1/wilayah/setup-district/KODE_KECAMATAN_7DIGIT" \
  -H "Authorization: Bearer rahasia-token-wilayah-pusat-2026"
```

**Data yang diterima dalam satu response:**
- ✅ Batas poligon **Kabupaten/Kota** (GeoJSON MultiPolygon)
- ✅ Batas poligon **Kecamatan** (GeoJSON MultiPolygon)
- ✅ Semua **Desa/Kelurahan** dalam kecamatan + poligon masing-masing
- ✅ Info **Provinsi** (kode, nama, koordinat pusat)

**Workflow yang disarankan:**
```
1. docker compose up -d --build
2. php artisan wilayah:import        ← satu kali, ~30-60 menit
3. Dari app Puskesmas: GET /api/v1/wilayah/setup-district/{kode}
4. Simpan GeoJSON ke DB lokal Puskesmas
5. Gunakan data lokal untuk rendering peta — tidak perlu query ulang
```

**Cara cari kode kecamatan:**
```bash
# Filter districts.json berdasarkan kode kabupaten (4 digit pertama)
python3 -c "
import json
with open('database/data/districts.json') as f:
    data = json.load(f)
# Contoh: cari kecamatan di Kab. Bandung (kode 3204)
for d in data:
    if str(d['id']).startswith('3204'):
        print(d['id'], d['name'])
"
```

---

## ⚠️ Catatan Penting & Potensi Masalah

### 1. Container Restarting (error saat `php artisan wilayah:import`)
Penyebab: MySQL belum selesai inisialisasi ketika container `app` mencoba connect.
```bash
docker compose logs db          # lihat status MySQL
docker compose restart app      # restart setelah MySQL healthy
docker compose ps               # pastikan semua STATUS = running
```

### 2. Import `villages.json` Sangat Berat
345.7 MB dengan 83.762 record + polygon kompleks. Estimasi: **30–90 menit**.
Monitor progress dengan progress bar di terminal.

### 3. Polygon Provinsi Selalu NULL
Kode import mengisi `'area' => null` untuk provinsi — polygon provinsi tidak diimport
meskipun data ada di JSON. Perlu modifikasi `ImportWilayahCommand.php` jika butuh polygon provinsi.

### 4. Flip Koordinat Penting
JSON source: `[lat, lon]` → GeoJSON standard: `[lon, lat]`.
`GeoJsonHelper` sudah menangani ini. Jangan bypass helper ini saat custom import.

### 5. Cache API Perlu Dibersihkan Setelah Reimport
```bash
docker compose exec app php artisan cache:clear
```

### 6. Token Default Tidak Aman
Ganti `MASTER_API_TOKEN` di `.env` sebelum expose ke jaringan:
```bash
# Generate token baru
openssl rand -base64 32
```

---

## 📋 Kode BPS — Cara Membaca Hierarki

```
Provinsi  : 2 digit   → "32"           = Jawa Barat
Kab/Kota  : 4 digit   → "3204"         = Kab. Bandung
Kecamatan : 7 digit   → "3204010"      = Kec. Cimenyan
Desa      : 10 digit  → "3204010001"   = Desa di Cimenyan
```

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────┐
│          wilayah-server-pusat (Docker Compose)          │
│                                                         │
│  Nginx:8080 ──► PHP-FPM Laravel 11 ──► MySQL 8 Spatial │
│                       │                    │            │
│                  File Cache            SPATIAL INDEX    │
│                  (30 hari)             ST_AsGeoJSON()   │
└───────────────────────┬─────────────────────────────────┘
                        │ HTTP REST API + Bearer Token
                        │
┌───────────────────────┴─────────────────────────────────┐
│              Aplikasi Sistem Puskesmas                  │
│                                                         │
│  Setup: GET /api/v1/wilayah/setup-district/{code}       │
│  → Simpan GeoJSON ke DB lokal                           │
│  → Render peta menggunakan data lokal                   │
└─────────────────────────────────────────────────────────┘
```

---

*Dibuat: 2026-09-21 | Metode: Deep scan seluruh codebase*
