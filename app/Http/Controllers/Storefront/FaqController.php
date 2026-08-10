<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::query()->whereNull('product_id')->where('is_active', true)->orderBy('sort_order')->get();

        return view('storefront.faqs.index', compact('faqs'));
    }
}