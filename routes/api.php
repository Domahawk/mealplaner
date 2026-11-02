<?php

use App\Http\Controllers\ReadinessController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
});

Route::get('/ready', ReadinessController::class);
