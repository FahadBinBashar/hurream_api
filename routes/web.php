<?php

use App\Core\Request as CoreRequest;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Artisan;
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

Route::get('/receipt/{receipt_no}', function (\Illuminate\Http\Request $request, string $receipt_no) {
    $controller = app(DocumentController::class);

    return $controller->receipt(CoreRequest::fromIlluminate($request), ['receipt_no' => $receipt_no]);
});

Route::get('/invoice/{invoice_no}', function (\Illuminate\Http\Request $request, string $invoice_no) {
    $controller = app(DocumentController::class);

    return $controller->invoice(CoreRequest::fromIlluminate($request), ['invoice_no' => $invoice_no]);
});
