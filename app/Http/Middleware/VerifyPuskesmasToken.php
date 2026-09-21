<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPuskesmasToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-KEY');
        $validToken = env('MASTER_API_TOKEN', 'rahasia-token-wilayah-pusat-2026');

        if (!$token || $token !== $validToken) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Token API Puskesmas tidak valid atau belum disertakan.',
            ], 401);
        }

        return $next($request);
    }
}