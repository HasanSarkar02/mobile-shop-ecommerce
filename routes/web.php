<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('/', fn () => tenant() ? view('storefront.home') : view('platform.home'));

Route::middleware('tenant')->group(function (): void {
    require __DIR__.'/tenant.php';
});