# PANDUAN INTEGRASI CLIENT (SISTEM PUSKESMAS)

### Modul Sinkronisasi Wilayah Kerja & Pemetaan Spasial Penyakit

Dokumen ini merupakan panduan teknis resmi bagi tim pengembang aplikasi Puskesmas untuk mengintegrasikan data spasial batas wilayah kerja (Kecamatan dan Desa/Kelurahan) dari **Server Pusat Wilayah** ke dalam database lokal Puskesmas, serta implementasi pemetaan sebaran penyakit berbasis GIS (Choropleth Risk Mapping & Point Clustering).

---

## 1. Arsitektur & Prinsip Kerja

```
+-----------------------------------+                     +-----------------------------------+
|        SERVER PUSAT WILAYAH       |                     |        APLIKASI PUSKESMAS         |
|      (Master GIS Repository)      |                     |    (Isolated Local Application)   |
|                                   |                     |                                   |
|  - 38 Provinsi (Poligon Nasional) |  GET /setup-district|  - 1 Provinsi (Metadata Induk)    |
|  - 514 Kabupaten / Kota           |  ==================>|  - 1 Kabupaten (Metadata Induk)   |
|  - 7.285 Kecamatan                |  Payload < 500 KB   |  - 1 Kecamatan Wilayah Kerja      |
|  - 83.762 Desa / Kelurahan        |  (GeoJSON WGS 84)   |  - 5 s/d 30 Desa / Kelurahan      |
|                                   |                     |  - Data Kasus Pasien Puskesmas    |
+-----------------------------------+                     +-----------------------------------+
```

### Prinsip Utama:
1. **Zero Dead-Weight:** Database lokal Puskesmas **tidak perlu menyimpan** 83.000+ desa se-Indonesia. Database Puskesmas hanya menyimpan 1 kecamatan dan 5–30 desa binaan yang menjadi wilayah kerjanya. Beban penyimpanan di Puskesmas sangat ringan (< 1 MB data spasial).
2. **Pre-Normalized GeoJSON (SRID 4326 / WGS 84):** Seluruh poligon dari Server Pusat telah berstandar `MultiPolygon` dengan urutan koordinat GeoJSON `[Longitude, Latitude]`.
3. **On-Demand One-Time Setup:** Data wilayah ditarik sekali saat inisialisasi aplikasi Puskesmas via Artisan CLI Command (`php artisan puskesmas:setup-wilayah`).
4. **Point-in-Polygon Detection:** Saat petugas menginput kasus pasien dengan mengeklik titik di peta, sistem secara otomatis mendeteksi nama desa pasien tanpa perlu memilih dropdown desa secara manual.

---

## 2. Spesifikasi API Server Pusat

* **Base URL Production / Server:** `http://45.127.134.12:8088` (atau domain internal Dinkes jika telah dipasang reverse proxy).
* **Autentikasi:** Bearer Token atau Header `X-API-KEY`.
* **Default API Token:** `rahasia-token-wilayah-pusat-2026`

---

### Endpoint 1: Health Check (Pengecekan Server Aktif)

Digunakan untuk memeriksa apakah Server Pusat dan database spasialnya sedang aktif dan dapat diakses dari jaringan Puskesmas.

* **URL:** `GET /api/health`
* **Autentikasi:** Tidak diperlukan (Public).
* **Contoh Request:**
  ```bash
  curl -X GET http://45.127.134.12:8088/api/health
  ```
* **Contoh Respon (`200 OK`):**
  ```json
  {
    "status": "ok",
    "timestamp": "2026-09-21T07:45:00.000000Z",
    "database": "connected"
  }
  ```

---

### Endpoint 2: Setup District Wilayah Kerja

Mengunduh hierarki lengkap untuk wilayah kerja Puskesmas: data Provinsi, Kabupaten/Kota, Kecamatan terpilih, dan seluruh Desa/Kelurahan di bawah kecamatan tersebut (lengkap dengan GeoJSON MultiPolygon).

* **URL:** `GET /api/v1/wilayah/setup-district/{code}`
* **Method:** `GET`
* **URL Parameter:** 
  * `code`: 6 digit kode Kecamatan Kemendagri / BPS (Contoh: `110101` untuk Kec. Bakongan, `320101` untuk Kec. Cibinong, `317101` untuk Kec. Gambir).
* **Headers:**
  ```http
  Authorization: Bearer rahasia-token-wilayah-pusat-2026
  Accept: application/json
  ```
  *(Catatan: Header `X-API-KEY: rahasia-token-wilayah-pusat-2026` juga didukung).*

* **Contoh Request cURL:**
  ```bash
  curl -X GET http://45.127.134.12:8088/api/v1/wilayah/setup-district/110101 \
    -H "Authorization: Bearer rahasia-token-wilayah-pusat-2026" \
    -H "Accept: application/json"
  ```

* **Struktur Respon (`200 OK`):**
  ```json
  {
    "status": "success",
    "data": {
      "province": {
        "id": 11,
        "code": "11",
        "name": "Aceh",
        "latitude": 4.2257285,
        "longitude": 96.9118740,
        "geojson": "{\"type\":\"MultiPolygon\",\"coordinates\":[[[[...]]]]}"
      },
      "regency": {
        "id": 1101,
        "province_id": 11,
        "code": "1101",
        "name": "Kabupaten Aceh Selatan",
        "latitude": 3.1618538,
        "longitude": 97.4365177,
        "geojson": "{\"type\":\"MultiPolygon\",\"coordinates\":[[[[...]]]]}"
      },
      "district": {
        "id": 110101,
        "regency_id": 1101,
        "code": "110101",
        "name": "Bakongan",
        "latitude": 2.9603257,
        "longitude": 97.4608730,
        "geojson": "{\"type\":\"MultiPolygon\",\"coordinates\":[[[[97.4385,2.9066],...]]]]}"
      },
      "villages": [
        {
          "id": 1101012001,
          "district_id": 110101,
          "code": "1101012001",
          "name": "Keude Bakongan",
          "latitude": 2.9310948,
          "longitude": 97.4845840,
          "geojson": "{\"type\":\"MultiPolygon\",\"coordinates\":[[[[97.4862,2.9382],...]]]]}"
        },
        {
          "id": 1101012002,
          "district_id": 110101,
          "code": "1101012002",
          "name": "Ujong Mangki",
          "latitude": 2.9150000,
          "longitude": 97.4920000,
          "geojson": "{\"type\":\"MultiPolygon\",\"coordinates\":[[[[...]]]]}"
        }
      ]
    }
  }
  ```

> [!NOTE]
> Server Pusat juga mengirimkan data `geojson` pada objek `province` dan `regency`. Namun pada database lokal Puskesmas, kita **tidak perlu** menyimpan poligon provinsi (cukup metadata kode, nama, dan lat/lng) guna menghemat kapasitas database, karena poligon satu provinsi utuh berukuran beberapa Megabyte.

---

## 3. Prasyarat Sistem Lokal Puskesmas

* **PHP:** Versi 8.2 atau lebih baru.
* **Database:** **MySQL 8.0+** atau MariaDB 10.5+ (Wajib mendukung tipe data spasial `GEOMETRY` / `MULTIPOLYGON`, fungsi `ST_Contains`, `ST_GeomFromGeoJSON`, dan SRID 4326).
* **Framework:** Laravel 10 / 11 / 12.

> [!IMPORTANT]
> **Aturan MySQL 8 Spatial Index:**
> Di MySQL 8 (InnoDB), indeks spasial (`SPATIAL INDEX`) mensyaratkan kolom berstatus `NOT NULL`. Karena data poligon desa/kecamatan bisa saja memiliki entitas tanpa koordinat poligon, kolom `area` pada tabel wilayah dibuat `nullable()` tanpa indeks spasial poligon (tabel wilayah Puskesmas hanya berisi 10–30 baris, sehingga query poligon berlangsung instan < 1 milidetik). Sebaliknya, pada tabel `disease_cases`, kolom `location` (titik pasien) berstatus `NOT NULL` sehingga wajib diberi `SPATIAL INDEX` untuk performa pencarian ribuan titik pasien.

---

## 4. Panduan Implementasi di Laravel Puskesmas

### Langkah 1: Buat Migration Database Wilayah & Kasus

Jalankan perintah di terminal project Laravel Puskesmas:

```bash
php artisan make:migration create_wilayah_and_disease_tables
```

Ganti isi migration dengan skema berikut:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Provinsi (Cukup metadata induk tanpa beban poligon)
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // 2. Tabel Kabupaten/Kota
        Schema::create('regencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->char('code', 4)->unique();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->geometry('area', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();
        });

        // 3. Tabel Kecamatan (Wilayah Kerja Utama Puskesmas)
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regency_id')->constrained('regencies')->cascadeOnDelete();
            $table->char('code', 7)->unique();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->geometry('area', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();
        });

        // 4. Tabel Desa/Kelurahan (Batas Kerja Desa Binaan Puskesmas)
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->char('code', 10)->unique();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->geometry('area', subtype: 'multipolygon', srid: 4326)->nullable();
            $table->timestamps();
        });

        // 5. Tabel Kasus Penyakit Pasien (Titik Koordinat Geografis)
        Schema::create('disease_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->nullOnDelete();
            $table->string('case_number', 50)->unique();
            $table->string('patient_name')->nullable();
            $table->string('disease_type', 50); // Contoh: 'DBD', 'TBC', 'Diare', 'Campak'
            $table->date('diagnosed_at');
            $table->enum('status', ['aktif', 'sembuh', 'meninggal'])->default('aktif');
            // Titik Koordinat Rumah Pasien (NOT NULL untuk Spatial Index)
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->spatialIndex('location');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disease_cases');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
    }
};
```

Jalankan migrasi:
```bash
php artisan migrate
```

---

### Langkah 2: Buat Eloquent Models

Buat model-model berikut di direktori `app/Models/`:

#### `app/Models/Province.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['id', 'code', 'name', 'latitude', 'longitude'];

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class);
    }
}
```

#### `app/Models/Regency.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regency extends Model
{
    protected $fillable = ['id', 'province_id', 'code', 'name', 'latitude', 'longitude', 'area'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
```

#### `app/Models/District.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = ['id', 'regency_id', 'code', 'name', 'latitude', 'longitude', 'area'];

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }
}
```

#### `app/Models/Village.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Village extends Model
{
    protected $fillable = ['id', 'district_id', 'code', 'name', 'latitude', 'longitude', 'area'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function diseaseCases(): HasMany
    {
        return $this->hasMany(DiseaseCase::class);
    }
}
```

#### `app/Models/DiseaseCase.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiseaseCase extends Model
{
    protected $fillable = [
        'village_id',
        'case_number',
        'patient_name',
        'disease_type',
        'diagnosed_at',
        'status',
        'location',
    ];

    protected $casts = [
        'diagnosed_at' => 'date',
    ];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
```

---

### Langkah 3: Konfigurasi Kredensial Server Pusat

Tambahkan variabel konfigurasi pada file `.env` sistem Puskesmas:

```env
# URL & Token Server Pusat GIS Wilayah
PUSAT_API_URL=http://45.127.134.12:8088
PUSAT_API_TOKEN=rahasia-token-wilayah-pusat-2026
```

Daftarkan variabel tersebut di dalam file `config/services.php`:

```php
// Tambahkan di dalam array 'return [...]':
'pusat' => [
    'url'   => rtrim(env('PUSAT_API_URL', 'http://45.127.134.12:8088'), '/'),
    'token' => env('PUSAT_API_TOKEN', 'rahasia-token-wilayah-pusat-2026'),
],
```

---

### Langkah 4: Buat Artisan Command Sinkronisasi Wilayah

Buat file command via terminal:

```bash
php artisan make:command SetupWilayahCommand
```

Buka file `app/Console/Commands/SetupWilayahCommand.php` dan isi dengan kode berikut:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SetupWilayahCommand extends Command
{
    protected $signature = 'puskesmas:setup-wilayah {--district= : Kode 6-digit Kecamatan wilayah kerja (contoh: 110101)}';
    protected $description = 'Inisialisasi batas wilayah kerja Puskesmas (Kecamatan & Desa) dari Server Pusat';

    public function handle(): int
    {
        $districtCode = $this->option('district');

        if (!$districtCode) {
            $districtCode = $this->ask('Masukkan 6-digit Kode Kecamatan (contoh: 110101)');
        }

        $districtCode = trim($districtCode);
        $baseUrl  = config('services.pusat.url');
        $apiToken = config('services.pusat.token');
        $endpoint = "{$baseUrl}/api/v1/wilayah/setup-district/{$districtCode}";

        $this->info("=================================================");
        $this->info("  SETUP WILAYAH KERJA PUSKESMAS DARI SERVER PUSAT");
        $this->info("=================================================");
        $this->line("Menghubungi: {$endpoint}");

        try {
            $response = Http::withToken($apiToken)
                ->timeout(60)
                ->acceptJson()
                ->get($endpoint);
        } catch (\Exception $e) {
            $this->error("Koneksi gagal: " . $e->getMessage());
            return Command::FAILURE;
        }

        if ($response->status() === 401) {
            $this->error("Gagal Autentikasi (401)! Periksa kembali PUSAT_API_TOKEN di file .env Anda.");
            return Command::FAILURE;
        }

        if ($response->status() === 404) {
            $this->error("Kode Kecamatan '{$districtCode}' tidak ditemukan di database Server Pusat!");
            return Command::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Gagal terhubung ke Server Pusat! HTTP Status: " . $response->status());
            $this->line($response->json('message') ?? $response->body());
            return Command::FAILURE;
        }

        $payload = $response->json('data');

        if (empty($payload['district'])) {
            $this->error("Format respon tidak valid: data kecamatan tidak ditemukan.");
            return Command::FAILURE;
        }

        $this->info("Data berhasil diterima! Menyimpan ke database lokal...");

        DB::transaction(function () use ($payload) {
            // 1. Simpan Metadata Provinsi
            $p = $payload['province'];
            DB::table('provinces')->updateOrInsert(
                ['id' => $p['id']],
                [
                    'code'       => (string) $p['code'],
                    'name'       => $p['name'],
                    'latitude'   => $p['latitude'] ?? null,
                    'longitude'  => $p['longitude'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $this->line("✓ Provinsi: {$p['name']}");

            // 2. Simpan Kabupaten/Kota
            $r = $payload['regency'];
            $regencyArea = !empty($r['geojson']) 
                ? DB::raw("ST_SRID(ST_GeomFromGeoJSON('" . addslashes($r['geojson']) . "', 1, 4326), 4326)") 
                : null;

            DB::table('regencies')->updateOrInsert(
                ['id' => $r['id']],
                [
                    'province_id' => $r['province_id'] ?? $p['id'],
                    'code'        => (string) $r['code'],
                    'name'        => $r['name'],
                    'latitude'    => $r['latitude'] ?? null,
                    'longitude'   => $r['longitude'] ?? null,
                    'area'        => $regencyArea,
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
            $this->line("✓ Kabupaten: {$r['name']}");

            // 3. Simpan Kecamatan Wilayah Kerja
            $d = $payload['district'];
            $districtArea = !empty($d['geojson']) 
                ? DB::raw("ST_SRID(ST_GeomFromGeoJSON('" . addslashes($d['geojson']) . "', 1, 4326), 4326)") 
                : null;

            DB::table('districts')->updateOrInsert(
                ['id' => $d['id']],
                [
                    'regency_id' => $d['regency_id'] ?? $r['id'],
                    'code'       => (string) $d['code'],
                    'name'       => $d['name'],
                    'latitude'   => $d['latitude'] ?? null,
                    'longitude'  => $d['longitude'] ?? null,
                    'area'       => $districtArea,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $this->line("✓ Kecamatan: {$d['name']}");

            // 4. Simpan Seluruh Desa/Kelurahan di Wilayah Kerja Puskesmas
            $villages = $payload['villages'] ?? [];
            $this->line("Menyimpan " . count($villages) . " Desa/Kelurahan binaan...");

            $bar = $this->output->createProgressBar(count($villages));
            $bar->start();

            foreach ($villages as $v) {
                $villageArea = !empty($v['geojson']) 
                    ? DB::raw("ST_SRID(ST_GeomFromGeoJSON('" . addslashes($v['geojson']) . "', 1, 4326), 4326)") 
                    : null;

                DB::table('villages')->updateOrInsert(
                    ['id' => $v['id']],
                    [
                        'district_id' => $v['district_id'] ?? $d['id'],
                        'code'        => (string) $v['code'],
                        'name'        => $v['name'],
                        'latitude'    => $v['latitude'] ?? null,
                        'longitude'   => $v['longitude'] ?? null,
                        'area'        => $villageArea,
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        });

        $this->newLine();
        $this->info("=================================================");
        $this->info("✓ INSIALISASI WILAYAH KERJA PUSKESMAS SELESAI!");
        $this->info("=================================================");
        return Command::SUCCESS;
    }
}
```

#### Cara Menjalankan Sinkronisasi:

```bash
# Contoh untuk Kecamatan Bakongan (Kode: 110101)
php artisan puskesmas:setup-wilayah --district=110101
```

---

## 5. Fitur Otomatisasi Input Kasus (Point-in-Polygon Desa)

> [!WARNING]
> **PENTING: Urutan Koordinat Sumbu MySQL 8 (SRID 4326)**
> Pada standar WKT (Well-Known Text) dengan koordinat geografis EPSG:4326, MySQL 8 secara default memperlakukan sumbu pertama sebagai **Latitude** dan sumbu kedua sebagai **Longitude**:
> `POINT(Latitude Longitude)`
> * Jika Anda memasukkan `POINT(106.8456 -6.2088)` tanpa opsi, MySQL 8 akan gagal dengan error: `Latitude 106.845600 is out of range... It must be within [-90.000000, 90.000000]`.
> * Gunakan urutan `POINT({$lat} {$lng})` dengan `ST_PointFromText(..., 4326)`, ATAU tentukan opsi eksplisit: `ST_PointFromText('POINT({$lng} {$lat})', 4326, 'axis-order=long-lat')`.

### Implementasi Controller Input Kasus (`DiseaseCaseController.php`):

Saat petugas Puskesmas mengklik titik lokasi rumah pasien pada peta, sistem akan menggunakan fungsi `ST_Contains` untuk **secara otomatis menentukan ID desa/kelurahan** pasien:

```php
<?php

namespace App\Http\Controllers;

use App\Models\DiseaseCase;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiseaseCaseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'case_number'  => 'required|string|unique:disease_cases,case_number',
            'patient_name' => 'nullable|string|max:255',
            'disease_type' => 'required|string', // Contoh: 'DBD', 'TBC', 'Diare'
            'diagnosed_at' => 'required|date',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;

        // 1. Deteksi Poligon Desa yang melingkupi titik koordinat pasien
        // Menggunakan opsi axis-order=long-lat agar urutan (Longitude Latitude) diterima dengan aman di MySQL 8
        $matchedVillage = Village::whereNotNull('area')
            ->whereRaw(
                "ST_Contains(area, ST_PointFromText(?, 4326, 'axis-order=long-lat'))",
                ["POINT({$lng} {$lat})"]
            )
            ->first();

        // 2. Simpan Data Kasus Pasien
        $case = new DiseaseCase();
        $case->village_id   = $matchedVillage?->id; // Otomatis terhubung ke desa terkait
        $case->case_number  = $request->case_number;
        $case->patient_name = $request->patient_name;
        $case->disease_type = $request->disease_type;
        $case->diagnosed_at = $request->diagnosed_at;
        $case->status       = 'aktif';
        // Simpan titik geometri ke MySQL
        $case->location     = DB::raw("ST_PointFromText('POINT({$lng} {$lat})', 4326, 'axis-order=long-lat')");
        $case->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data kasus pasien berhasil disimpan.',
            'data'    => [
                'case_number'  => $case->case_number,
                'village_name' => $matchedVillage?->name ?? 'Di luar batas wilayah kerja desa binaan',
                'village_code' => $matchedVillage?->code ?? null,
            ],
        ], 201);
    }
}
```

---

## 6. Penyajian Data Spasial ke Peta Frontend (Leaflet JS)

### Controller API Distribusi Peta (`MapDistributionController.php`)

Endpoint ini menghasilkan format gabungan:
1. **Layer Poligon (GeoJSON FeatureCollection):** Seluruh desa di wilayah kerja beserta atribut agregat total kasus aktif (untuk pewarnaan zonasi risiko / Choropleth).
2. **Layer Titik Kasus (Points):** Titik lokasi setiap kasus individual untuk penandaan marker / cluster.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapDistributionController extends Controller
{
    public function getDistributionData(Request $request): JsonResponse
    {
        $disease = $request->query('disease', 'DBD');

        // 1. Poligon Desa + Agregat Total Kasus per Desa
        $villages = DB::table('villages')
            ->whereNotNull('area')
            ->select('id', 'code', 'name', 'latitude', 'longitude')
            ->selectRaw('ST_AsGeoJSON(area) as geojson')
            ->selectSub(function ($query) use ($disease) {
                $query->from('disease_cases')
                    ->whereColumn('disease_cases.village_id', 'villages.id')
                    ->where('disease_type', $disease)
                    ->where('status', 'aktif')
                    ->selectRaw('COUNT(*)');
            }, 'total_cases')
            ->get()
            ->map(function ($v) {
                return [
                    'type' => 'Feature',
                    'properties' => [
                        'id'          => $v->id,
                        'code'        => $v->code,
                        'name'        => $v->name,
                        'total_cases' => (int) $v->total_cases,
                    ],
                    'geometry' => json_decode($v->geojson),
                ];
            });

        // 2. Titik Geografis Kasus Pasien
        // Catatan: Di MySQL 8, gunakan ST_Latitude() dan ST_Longitude() untuk menghindari tertukarnya sumbu X dan Y
        $cases = DB::table('disease_cases')
            ->where('disease_type', $disease)
            ->where('status', 'aktif')
            ->select('case_number', 'patient_name', 'disease_type', 'diagnosed_at')
            ->selectRaw('ST_Latitude(location) as latitude, ST_Longitude(location) as longitude')
            ->get();

        return response()->json([
            'type'        => 'FeatureCollection',
            'disease'     => $disease,
            'features'    => $villages,
            'cases'       => $cases,
        ]);
    }
}
```

---

### Tampilan Frontend (Blade + Leaflet.js + MarkerCluster)

Buat tampilan peta pada view Blade Puskesmas (contoh: `resources/views/map/index.blade.php`):

```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peta Sebaran Penyakit Wilayah Kerja Puskesmas</title>

    <!-- Leaflet & MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #map { width: 100vw; height: 100vh; }
        .legend {
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            line-height: 24px;
            font-size: 13px;
        }
        .legend i {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            opacity: 0.8;
            border-radius: 3px;
        }
    </style>
</head>
<body>

<div id="map"></div>

<!-- Leaflet & MarkerCluster JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
    // 1. Inisialisasi Peta
    const map = L.map('map').setView([-2.5489, 118.0149], 5); // Default view Indonesia

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors | Puskesmas GIS'
    }).addTo(map);

    // 2. Fungsi Skema Warna Choropleth
    function getColor(cases) {
        if (cases > 10) return '#bd0026'; // Sangat Tinggi (Merah Pekat)
        if (cases > 5)  return '#f03b20'; // Tinggi (Merah)
        if (cases > 2)  return '#fd8d3c'; // Sedang (Oranye)
        if (cases > 0)  return '#fecc5c'; // Rendah (Kuning)
        return '#2ecc71';                 // Nol Kasus (Hijau)
    }

    // 3. Fetch Data Distribusi dari Controller
    fetch('/api/map-distribution?disease=DBD')
        .then(res => res.json())
        .then(data => {
            // LAYER 1: Choropleth Poligon Desa
            const polygonLayer = L.geoJSON(data, {
                style: function (feature) {
                    const cases = feature.properties.total_cases;
                    return {
                        fillColor: getColor(cases),
                        weight: 2,
                        opacity: 1,
                        color: 'white',
                        dashArray: '3',
                        fillOpacity: 0.5
                    };
                },
                onEachFeature: function (feature, layer) {
                    layer.bindTooltip(
                        `<strong>Desa ${feature.properties.name}</strong><br>` +
                        `Jumlah Kasus Aktif: <b>${feature.properties.total_cases}</b>`,
                        { sticky: true }
                    );

                    layer.on('mouseover', function (e) {
                        const target = e.target;
                        target.setStyle({ weight: 4, color: '#333', fillOpacity: 0.7 });
                    });
                    layer.on('mouseout', function (e) {
                        polygonLayer.resetStyle(e.target);
                    });
                }
            }).addTo(map);

            // Fokus kamera peta secara otomatis ke seluruh poligon wilayah kerja
            if (polygonLayer.getBounds().isValid()) {
                map.fitBounds(polygonLayer.getBounds(), { padding: [20, 20] });
            }

            // LAYER 2: Marker Clustering Titik Kasus Pasien
            const clusterGroup = L.markerClusterGroup();

            data.cases.forEach(c => {
                if (c.latitude && c.longitude) {
                    const marker = L.marker([c.latitude, c.longitude])
                        .bindPopup(
                            `<b>Kasus: ${c.case_number}</b><br>` +
                            `Penyakit: ${c.disease_type}<br>` +
                            `Pasien: ${c.patient_name ?? '-'}<br>` +
                            `Tgl Diagnosa: ${c.diagnosed_at}`
                        );
                    clusterGroup.addLayer(marker);
                }
            });

            map.addLayer(clusterGroup);
        });

    // 4. Tambahkan Legenda Zonasi
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
        const div = L.DomUtil.create('div', 'legend');
        const grades = [0, 1, 3, 6, 11];
        const labels = ['<strong>Tingkat Kasus</strong><br>'];

        div.innerHTML += labels[0];
        div.innerHTML += '<i style="background:#2ecc71"></i> 0 Kasus (Aman)<br>';
        div.innerHTML += '<i style="background:#fecc5c"></i> 1 - 2 Kasus (Waspada)<br>';
        div.innerHTML += '<i style="background:#fd8d3c"></i> 3 - 5 Kasus (Sedang)<br>';
        div.innerHTML += '<i style="background:#f03b20"></i> 6 - 10 Kasus (Tinggi)<br>';
        div.innerHTML += '<i style="background:#bd0026"></i> > 10 Kasus (Kejadian Luar Biasa)<br>';
        return div;
    };
    legend.addTo(map);
</script>

</body>
</html>
```

---

## 7. Troubleshooting & FAQ Pengembang

| Masalah / Error | Penyebab Utama | Solusi |
|---|---|---|
| `ERROR 3617 (22S03): Latitude ... is out of range in function st_pointfromtext` | Di MySQL 8, urutan sumbu WKT secara default adalah `(Latitude Longitude)`. Memasukkan koordinat bujur Indonesia (~95 s/d 141) pada sumbu pertama melanggar rentang latitude `[-90, 90]`. | Gunakan opsi `'axis-order=long-lat'`: `ST_PointFromText('POINT(lng lat)', 4326, 'axis-order=long-lat')` atau balik urutan menjadi `POINT(lat lng)`. |
| `ERROR 1252 (42000): All parts of a SPATIAL index must be NOT NULL` | Menambahkan `$table->spatialIndex('area')` pada kolom poligon yang bernilai `nullable()`. | Lepaskan `$table->spatialIndex('area')` dari tabel wilayah client. Untuk 10–30 desa binaan, indeks spasial tidak diperlukan karena pencarian poligon di memori hanya membutuhkan < 1 milidetik. |
| Koordinat pasien terbalik (muncul di kutub atau laut lepas) | Menggunakan `ST_X(location) as longitude` dan `ST_Y(location) as latitude`. Pada SRID 4326 di MySQL 8, sumbu X adalah Latitude dan sumbu Y adalah Longitude. | Gunakan fungsi bawaan yang spesifik: `ST_Latitude(location) as latitude` dan `ST_Longitude(location) as longitude`. |
| HTTP `401 Unauthorized` pada artisan command | Nilai `PUSAT_API_TOKEN` di `.env` Puskesmas belum sesuai dengan token Server Pusat. | Pastikan `PUSAT_API_TOKEN=rahasia-token-wilayah-pusat-2026` telah terpasang di `.env` dan jalankan `php artisan config:clear`. |
| HTTP `404 Not Found` pada setup district | Kode kecamatan tidak ada di master database Kemendagri / BPS. | Pastikan menggunakan 6-digit kode resmi kecamatan Kemendagri (contoh: `110101` untuk Bakongan). |
| Titik pasien mengembalikan `village_name: "Di luar batas wilayah kerja desa binaan"` | Titik koordinat yang diklik petugas berada di luar batas poligon desa binaan kecamatan Puskesmas tersebut. | Pastikan titik koordinat berada di dalam kecamatan kerja Puskesmas. Jika pasien berasal dari luar wilayah, simpan `village_id = null`. |