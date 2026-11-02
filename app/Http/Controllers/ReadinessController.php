<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('SELECT 1');
        } catch (Throwable) {
            // Exceptions can contain credentials; never return or log their messages here.
            return response()->json([
                'status' => 'error',
                'database' => 'unavailable',
                'message' => 'Database connectivity is temporarily unavailable.',
            ], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'status' => 'ok',
            'database' => 'connected',
        ])->header('Cache-Control', 'no-store');
    }
}
