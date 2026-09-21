<?php

namespace App\Console\Commands;

use App\Helpers\GeoJsonHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportWilayahCommand extends Command
{
    protected $signature = 'wilayah:import {--path=database/data : Direktori penyimpanan file JSON}';
    protected $description = 'Import data master wilayah Indonesia (Provinsi, Kab/Kota, Kecamatan, Desa) ke MySQL Spasial';

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $basePath = base_path($this->option('path'));

        $this->info("=================================================");
        $this->info("   MEMULAI IMPORT DATA MASTER WILAYAH INDONESIA  ");
        $this->info("=================================================");

        $this->importProvinces($basePath);
        $this->importRegencies($basePath);
        $this->importDistricts($basePath);
        $this->importVillages($basePath);

        $this->newLine();
        $this->info("✓ Semua data wilayah berhasil diimpor ke database master!");
        return Command::SUCCESS;
    }

    /**
     * Membaca file JSON baik dalam format teks biasa (.json) maupun terkompresi (.json.gz)
     */
    private function readJsonData(string $basePath, string $filename): ?array
    {
        // 1. Cek versi terkompresi .json.gz
        $gzPath = "{$basePath}/{$filename}.json.gz";
        if (File::exists($gzPath)) {
            $content = gzdecode(File::get($gzPath));
            return json_decode($content, true);
        }

        // 2. Cek versi teks biasa .json
        $rawPath = "{$basePath}/{$filename}.json";
        if (File::exists($rawPath)) {
            $content = File::get($rawPath);
            return json_decode($content, true);
        }

        return null;
    }

    private function importProvinces(string $basePath): void
    {
        $items = $this->readJsonData($basePath, 'provinces');

        if ($items === null) {
            $this->warn("File provinces.json(.gz) tidak ditemukan, melewati provinsi.");
            return;
        }

        $this->line("Mengimpor " . count($items) . " Provinsi...");

        foreach ($items as $item) {
            $area = null;
            if (!empty($item['coordinates'])) {
                $geoJson = GeoJsonHelper::normalizeToMultiPolygonGeoJson($item['coordinates']);
                if ($geoJson) {
                    $area = DB::raw("ST_SRID(ST_GeomFromGeoJSON('{$geoJson}', 1, 4326), 4326)");
                }
            }

            DB::table('provinces')->updateOrInsert(
                ['code' => (string) $item['id']],
                [
                    'name'       => $item['name'],
                    'latitude'   => $item['latitude'] ?? null,
                    'longitude'  => $item['longitude'] ?? null,
                    'area'       => $area,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        $this->info("✓ Provinsi selesai.");
    }

    private function importRegencies(string $basePath): void
    {
        $items = $this->readJsonData($basePath, 'regencies');

        if ($items === null) {
            $this->warn("File regencies.json(.gz) tidak ditemukan, melewati kabupaten/kota.");
            return;
        }

        $this->line("Mengimpor " . count($items) . " Kabupaten/Kota...");

        $provinceMap = DB::table('provinces')->pluck('id', 'code')->toArray();

        foreach ($items as $item) {
            $provCode = (string) ($item['province_id'] ?? substr($item['id'], 0, 2));
            $provId = $provinceMap[$provCode] ?? null;

            if (!$provId) continue;

            $area = null;
            if (!empty($item['coordinates'])) {
                $geoJson = GeoJsonHelper::normalizeToMultiPolygonGeoJson($item['coordinates']);
                if ($geoJson) {
                    $area = DB::raw("ST_SRID(ST_GeomFromGeoJSON('{$geoJson}', 1, 4326), 4326)");
                }
            }

            DB::table('regencies')->updateOrInsert(
                ['code' => (string) $item['id']],
                [
                    'province_id' => $provId,
                    'name'        => $item['name'],
                    'latitude'    => $item['latitude'] ?? null,
                    'longitude'   => $item['longitude'] ?? null,
                    'area'        => $area,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }
        $this->info("✓ Kabupaten/Kota selesai.");
    }

    private function importDistricts(string $basePath): void
    {
        $items = $this->readJsonData($basePath, 'districts');

        if ($items === null) {
            $this->warn("File districts.json(.gz) tidak ditemukan, melewati kecamatan.");
            return;
        }

        $this->line("Mengimpor " . count($items) . " Kecamatan...");

        $regencyMap = DB::table('regencies')->pluck('id', 'code')->toArray();

        foreach ($items as $item) {
            $regCode = (string) ($item['regency_id'] ?? substr($item['id'], 0, 4));
            $regId = $regencyMap[$regCode] ?? null;

            if (!$regId) continue;

            $area = null;
            if (!empty($item['coordinates'])) {
                $geoJson = GeoJsonHelper::normalizeToMultiPolygonGeoJson($item['coordinates']);
                if ($geoJson) {
                    $area = DB::raw("ST_SRID(ST_GeomFromGeoJSON('{$geoJson}', 1, 4326), 4326)");
                }
            }

            DB::table('districts')->updateOrInsert(
                ['code' => (string) $item['id']],
                [
                    'regency_id' => $regId,
                    'name'       => $item['name'],
                    'latitude'   => $item['latitude'] ?? null,
                    'longitude'  => $item['longitude'] ?? null,
                    'area'       => $area,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        $this->info("✓ Kecamatan selesai.");
    }

    private function importVillages(string $basePath): void
    {
        // 1. Cek apakah ada file pecahan villages_part*.json* (termasuk .gz)
        $partFiles = glob("{$basePath}/villages_part*.json*");
        sort($partFiles);

        if (!empty($partFiles)) {
            $districtMap = DB::table('districts')->pluck('id', 'code')->toArray();
            $this->line("Ditemukan " . count($partFiles) . " file part Desa/Kelurahan.");

            foreach ($partFiles as $partFile) {
                $filename = basename($partFile);
                $this->line("Memproses {$filename}...");
                $content = str_ends_with($partFile, '.gz') 
                    ? gzdecode(File::get($partFile)) 
                    : File::get($partFile);
                $items = json_decode($content, true) ?? [];
                $this->processVillageBatch($items, $districtMap);
            }
            $this->info("✓ Desa/Kelurahan selesai.");
            return;
        }

        // 2. Fallback ke file utuh villages.json / villages.json.gz
        $items = $this->readJsonData($basePath, 'villages');
        if ($items === null) {
            $this->warn("File villages.json(.gz) tidak ditemukan, melewati desa.");
            return;
        }

        $districtMap = DB::table('districts')->pluck('id', 'code')->toArray();
        $this->line("Mengimpor " . count($items) . " Desa/Kelurahan se-Indonesia...");
        $this->processVillageBatch($items, $districtMap);
        $this->info("✓ Desa/Kelurahan selesai.");
    }

    private function processVillageBatch(array $items, array $districtMap): void
    {
        $total = count($items);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach (array_chunk($items, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $districtMap, $bar) {
                foreach ($chunk as $item) {
                    $distCode = (string) ($item['district_id'] ?? substr($item['id'], 0, 6));
                    $distId = $districtMap[$distCode] ?? null;

                    if (!$distId) {
                        $bar->advance();
                        continue;
                    }

                    $area = null;
                    if (!empty($item['coordinates'])) {
                        $geoJson = GeoJsonHelper::normalizeToMultiPolygonGeoJson($item['coordinates']);
                        if ($geoJson) {
                            $area = DB::raw("ST_SRID(ST_GeomFromGeoJSON('{$geoJson}', 1, 4326), 4326)");
                        }
                    }

                    DB::table('villages')->updateOrInsert(
                        ['code' => (string) $item['id']],
                        [
                            'district_id' => $distId,
                            'name'        => $item['name'],
                            'latitude'    => $item['latitude'] ?? null,
                            'longitude'   => $item['longitude'] ?? null,
                            'area'        => $area,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]
                    );
                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->newLine();
    }
}