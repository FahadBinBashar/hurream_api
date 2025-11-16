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
    return "HI Hurream";
});

Route::get('/clear-cache', function () {

    Artisan::call('optimize:clear');   // clears everything
    Artisan::call('cache:clear');      // application cache
    Artisan::call('config:clear');     // config cache
    Artisan::call('route:clear');      // route cache
    Artisan::call('view:clear');       // view cache
    Artisan::call('clear-compiled');   // compiled classes

    return "<h2>All Cache Cleared Successfully!</h2>";
});