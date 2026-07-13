<?php

use Illuminate\Support\Facades\Route;

Route::middleware('central')->group(function (): void {
    Route::get('/', fn () => view('platform.home'));
});

Route::middleware('tenant')->group(function (): void {
    require __DIR__.'/tenant.php';
});