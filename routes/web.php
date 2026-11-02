<?php

use Illuminate\Support\Facades\Route;

// Serving the worker at the root gives it the correct scope on every Laravel host.
Route::get('/sw.js', function () {
    abort_unless(is_file(public_path('build/sw.js')), 404);

    return response()->file(public_path('build/sw.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-store',
        'Service-Worker-Allowed' => '/',
    ]);
});

// Add backend routes above this route. Reserve framework and static namespaces.
Route::view('/{path?}', 'app')
    ->where('path', '(?!(?:api|up|build|storage|icons|sanctum|_ignition|_debugbar|horizon|telescope|livewire)(?:/|$))(?!.*\.[^/]+$).*')
    ->name('spa');
