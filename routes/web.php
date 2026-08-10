<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['central', 'throttle:20,1'])->group(function (): void {
    Route::get('/', fn () => view('platform.home'))->name('platform.home');
    Route::get('/signup', \App\Livewire\TenantSignupForm::class)->name('platform.signup');
});

Route::middleware('tenant')->group(function (): void {
    require __DIR__.'/tenant.php';
});