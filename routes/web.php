<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/link-storage', function () {
    Artisan::call('storage:link');
    return 'Storage linked!';
})->name('link-storage');

Route::get('/migrate-fresh', function () {
    Artisan::call('migrate:fresh');
    return 'Migrated fresh!';
})->name('migrate-fresh');

Route::get('/seed', function () {
    Artisan::call('db:seed');
    return 'Seeded!';
})->name('seed');

Route::prefix('/cache')->group(function () {
    Route::get('/config', function () {
        Artisan::call('config:cache');
        return 'Config cached!';
    })->name('cache-config');

    Route::get('/route', function () {
        Artisan::call('route:cache');
        return 'Route cached!';
    })->name('cache-route');

    Route::get('/all', function () {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        return 'All cached!';
    })->name('cache-all');
});

Route::prefix('/clear')->group(function () {
    Route::get('/cache', function () {
        Artisan::call('cache:clear');
        return 'Cache cleared!';
    })->name('clear-cache');

    Route::get('/config', function () {
        Artisan::call('config:clear');
        return 'Config cleared!';
    })->name('clear-config');

    Route::get('/route', function () {
        Artisan::call('route:clear');
        return 'Route cleared!';
    })->name('clear-route');

    Route::get('/all', function () {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        return 'All cleared!';
    })->name('clear-all');
});
