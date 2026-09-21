<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('wilayah:status', function () {
    $provinces = DB::table('provinces')->count();
    $regencies = DB::table('regencies')->count();
    $districts = DB::table('districts')->count();
    $villages = DB::table('villages')->count();

    $this->info("========================================");
    $this->info("   STATUS DATABASE MASTER WILAYAH GIS   ");
    $this->info("========================================");
    $this->line("1. Total Provinsi           : {$provinces}");
    $this->line("2. Total Kabupaten / Kota   : {$regencies}");
    $this->line("3. Total Kecamatan          : {$districts}");
    $this->line("4. Total Desa / Kelurahan   : {$villages}");
    $this->info("========================================");
})->purpose('Cek rekap jumlah data wilayah yang terdaftar');