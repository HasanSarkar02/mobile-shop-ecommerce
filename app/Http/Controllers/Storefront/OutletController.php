<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Outlet;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('storefront.outlets.index', [
            'outlets' => $outlets,
        ]);
    }
}
