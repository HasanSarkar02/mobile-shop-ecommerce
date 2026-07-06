<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => 'tenant: '.tenant()->name);