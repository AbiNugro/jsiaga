<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.jsiaga.device_token');
        $providedToken = (string) $request->header('X-Device-Token', '');

        if ($configuredToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token perangkat belum dikonfigurasi pada server.',
                'errors' => (object) [],
            ], 503);
        }

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Token perangkat tidak valid.',
                'errors' => (object) [],
            ], 401);
        }

        return $next($request);
    }
}
