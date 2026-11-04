<?php

use App\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

// These same-origin JSON endpoints deliberately use the web session/CSRF stack.
Route::prefix('api/auth')->controller(AuthenticationController::class)->group(function (): void {
    Route::get('/session', 'session');
    Route::get('/user', 'session')->middleware('auth');
    Route::post('/register', 'register')->middleware('throttle:registration');
    Route::post('/login', 'login')->middleware('throttle:login');
    Route::post('/logout', 'logout')->middleware('auth');
});

Route::view('/login', 'app')->name('login');
Route::view('/register', 'app')->name('register');
Route::view('/account', 'app')->middleware(['auth', 'cache.headers:no_store;private'])->name('account');

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
    ->where('path', '(?!(?:api|up|account|build|storage|icons|sanctum|_ignition|_debugbar|horizon|telescope|livewire)(?:/|$))(?!.*\.[^/]+$).*')
    ->name('spa');
