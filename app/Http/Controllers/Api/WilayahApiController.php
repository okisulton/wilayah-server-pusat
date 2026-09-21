<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WilayahApiController extends Controller
{
    public function getDistrictSetup(string $code): JsonResponse
    {
        $payload = Cache::remember("district_setup_payload_{$code}", 60 * 60 * 24 * 30, function () use ($code) {
            
            $district = DB::table('districts')
                ->select('id', 'regency_id', 'code', 'name', 'latitude', 'longitude')
                ->selectRaw('ST_AsGeoJSON(area) as geojson')
                ->where('code', $code)
                ->first();

            if (!$district) {
                return null;
            }

            $regency = DB::table('regencies')
                ->select('id', 'province_id', 'code', 'name', 'latitude', 'longitude')
                ->selectRaw('ST_AsGeoJSON(area) as geojson')
                ->where('id', $district->regency_id)
                ->first();

            $province = DB::table('provinces')
                ->select('id', 'code', 'name', 'latitude', 'longitude')
                ->selectRaw('ST_AsGeoJSON(area) as geojson')
                ->where('id', $regency->province_id)
                ->first();

            $villages = DB::table('villages')
                ->select('id', 'district_id', 'code', 'name', 'latitude', 'longitude')
                ->selectRaw('ST_AsGeoJSON(area) as geojson')
                ->where('district_id', $district->id)
                ->get();

            return [
                'province' => $province,
                'regency'  => $regency,
                'district' => $district,
                'villages' => $villages,
            ];
        });

        if (!$payload) {
            return response()->json([
                'status'  => 'error',
                'message' => "Kode kecamatan '{$code}' tidak ditemukan di database master.",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $payload,
        ]);
    }

    public function healthCheck(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected: ' . $e->getMessage();
        }

        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'database'  => $dbStatus,
        ]);
    }
}