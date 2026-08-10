<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class AccountOrderController extends Controller
{
    public function index()
    {
        $orders = Order::query()
            ->where('customer_id', Auth::guard('customer')->id())
            ->latest('placed_at')
            ->paginate(15);

        return view('storefront.account.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_unless($order->customer_id === Auth::guard('customer')->id(), 404);

        $order->load('items', 'payments', 'fulfillments', 'events');

        return view('storefront.account.orders.show', compact('order'));
    }
}