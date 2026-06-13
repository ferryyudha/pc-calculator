<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Device;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'API Key is missing.'
            ], 401);
        }

        $device = Device::where('api_key', $apiKey)->first();

        if (!$device) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API Key.'
            ], 401);
        }

        // Attach device to request for easy access in controllers
        $request->merge(['authenticated_device' => $device]);

        return $next($request);
    }
}
