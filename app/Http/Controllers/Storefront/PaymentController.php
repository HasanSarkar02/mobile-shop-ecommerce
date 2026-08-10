<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function pay(Order $order, PaymentGatewayService $gateway)
    {
        return redirect()->away($gateway->initiatePayment($order));
    }

    public function success(Request $request, PaymentGatewayService $gateway)
    {
        $gateway->handleCallback((string) $request->input('val_id'), (string) $request->input('tran_id'));

        $orderNumber = Str::after((string) $request->input('tran_id'), '-');

        return redirect()->route('storefront.checkout.confirmation', $orderNumber);
    }

    public function fail()
    {
        return redirect()->route('storefront.checkout')->with('error', 'Payment failed. Please try again or choose Cash on Delivery.');
    }

    public function cancel()
    {
        return redirect()->route('storefront.checkout')->with('error', 'Payment was cancelled.');
    }

    public function ipn(Request $request, PaymentGatewayService $gateway)
    {
        $gateway->handleCallback((string) $request->input('val_id'), (string) $request->input('tran_id'));

        return response('OK');
    }
}