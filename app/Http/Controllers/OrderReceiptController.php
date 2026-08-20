<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveSupportSession;
use App\Models\Order;
use App\Models\User;
use App\Presenters\OrderReceiptPresenter;
use Illuminate\View\View;

class OrderReceiptController extends Controller
{
    public function __invoke(Order $order): View
    {
        $tenant = tenant();
        $user = auth()->user();
        $supportMode = session()->has(ResolveSupportSession::SESSION_KEY);

        abort_unless($tenant !== null && $user instanceof User && $user->getAttribute('is_active') === true, 403);
        abort_unless(
            (int) $order->tenant_id === (int) $tenant->id
            && ((int) $user->tenant_id === (int) $tenant->id || ($supportMode && $user->getAttribute('is_platform_admin') === true)),
            404,
        );

        $order->load([
            'tenant.themeSettings',
            'tenant.settings',
            'tenant.primaryDomain',
            'customer',
            'items.serialNumbers',
            'payments.paymentMethod',
            'shippingMethod',
        ]);

        return view('receipts.order', [
            'receipt' => (new OrderReceiptPresenter($order))->data(),
            'backUrl' => route('filament.store.resources.orders.view', ['record' => $order]),
        ]);
    }
}
