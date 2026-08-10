<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function form()
    {
        return view('storefront.track-order.form');
    }

    public function show(Request $request)
    {
        $data = $request->validate([
            'order_number' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $order = Order::query()
            ->where('order_number', $data['order_number'])
            ->where(function ($query) use ($data): void {
                $query->where('guest_email', $data['email'])
                    ->orWhereHas('customer', fn ($c) => $c->where('email', $data['email']));
            })
            ->with(['items', 'fulfillments', 'events'])
            ->first();

        if (! $order) {
            return back()->withErrors(['order_number' => 'No matching order found. Please check your order number and email.']);
        }

        return view('storefront.track-order.result', compact('order'));
    }
}