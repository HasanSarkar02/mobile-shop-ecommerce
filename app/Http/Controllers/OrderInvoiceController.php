<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveSupportSession;
use App\Models\Order;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderInvoiceController extends Controller
{
    public function __invoke(Request $request, Order $order): StreamedResponse
    {
        $tenant = tenant();

        abort_unless($tenant !== null, 404);
        abort_unless((int) $order->tenant_id === (int) $tenant->id, 404);

        $customer = auth('customer')->user();
        $user = auth()->user();
        $supportMode = session()->has(ResolveSupportSession::SESSION_KEY);

        $isCustomerOwner = $customer !== null && (int) $order->getAttribute('customer_id') === (int) $customer->getAttribute('id');
        $isStoreOwner = $user instanceof User
            && $user->getAttribute('is_active') === true
            && ((int) $user->getAttribute('tenant_id') === (int) $tenant->id || ($supportMode && $user->getAttribute('is_platform_admin') === true));

        abort_unless($isCustomerOwner || $isStoreOwner, 403);

        $service = app(InvoiceService::class);

        try {
            $pdf = $service->pdfBytes($order);
        } catch (\RuntimeException $e) {
            abort(500, $e->getMessage());
        }

        $filename = $service->filename($order);

        return response()->streamDownload(
            fn () => print ($pdf),
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }
}
