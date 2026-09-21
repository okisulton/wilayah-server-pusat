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

        $this->importProvinces("{$basePath}/provinces.json");
        $this->importRegencies("{$basePath}/regencies.json");
        $this->importDistricts("{$basePath}/districts.json");
        $this->importVillages("{$basePath}/villages.json");

        $this->newLine();
        $this->info("✓ Semua data wilayah berhasil diimpor ke database master!");
        return Command::SUCCESS;
    }

    private function importProvinces(string $file): void
    {
        if (!File::exists($file)) {
            $this->warn("File {$file} tidak ditemukan, melewati provinsi.");
            return;
        }

        $items = json_decode(File::get($file), true) ?? [];
        $this->line("Mengimpor " . count($items) . " Provinsi...");

        foreach ($items as $item) {
            $area = null;
            if (!empty($item['coordinates'])) {
                $geoJson = GeoJsonHelper::normalizeToMultiPolygonGeoJson($item['coordinates']);
                $area = DB::raw("ST_SRID(ST_GeomFromGeoJSON('{$geoJson}', 1, 4326), 4326)");
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

    private function importRegencies(string $file): void
    {
        if (!File::exists($file)) return;
        $items = json_decode(File::get($file), true) ?? [];
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

    private function importDistricts(string $file): void
    {
        if (!File::exists($file)) return;
        $items = json_decode(File::get($file), true) ?? [];
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

    private function importVillages(string $file): void
    {
        if (!File::exists($file)) return;
        $items = json_decode(File::get($file), true) ?? [];
        $total = count($items);
        $this->line("Mengimpor {$total} Desa/Kelurahan se-Indonesia (Batching & In-Memory Lookup)...");

        $districtMap = DB::table('districts')->pluck('id', 'code')->toArray();

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
        $this->info("✓ Desa/Kelurahan selesai.");
    }
}