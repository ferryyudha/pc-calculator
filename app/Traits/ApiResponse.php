<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     */
    protected function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $response = ['success' => true, 'data' => $data];
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        return response()->json($response, $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $code   — Error code from blueprint §4.2
     * @param string $message — User-friendly message in Indonesian
     * @param mixed  $details — Optional technical details
     * @param int    $status  — HTTP status code
     */
    protected function error(string $code, string $message, mixed $details = null, int $status = 422): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        return response()->json(['success' => false, 'error' => $error], $status);
    }
}
