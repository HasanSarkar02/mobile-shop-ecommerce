<?php

use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('storefront.home');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('storefront.category');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('storefront.product');