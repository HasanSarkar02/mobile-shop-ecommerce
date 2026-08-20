<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class OrderReceiptPresenter
{
    public function __construct(private readonly Order $order) {}

    /**
     * Build receipt data strictly from persisted order snapshots and relations.
     * This presenter never writes records or consults current catalog pricing.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $currentTenant = tenant();
        $currency = (string) $this->order->getAttribute('currency_code');

        if ($currency === '') {
            $currency = (string) ($currentTenant?->getAttribute('currency') ?? 'BDT');
        }

        $tenant = $this->order->getRelationValue('tenant');
        $theme = $tenant instanceof Model ? $tenant->getRelationValue('themeSettings') : null;
        $settings = $tenant instanceof Model ? $tenant->getRelationValue('settings') : null;
        $payments = $this->order->getRelationValue('payments');
        $paid = $this->paidAmount($payments);
        $placedAt = $this->order->getAttribute('placed_at');
        $status = $this->order->getAttribute('status');
        $shippingMethod = $this->order->getRelationValue('shippingMethod');
        $primaryDomain = $tenant instanceof Model ? $tenant->getRelationValue('primaryDomain') : null;
        $logoPath = $theme instanceof Model ? $theme->getAttribute('logo_path') : null;
        $footer = $theme instanceof Model ? $theme->getAttribute('footer_text') : null;

        if (! filled($footer) && $settings instanceof Model) {
            $footer = $settings->getAttribute('order_confirmation_note');
        }

        return [
            'store' => [
                'name' => (string) ($tenant instanceof Model ? $tenant->getAttribute('name') : ($currentTenant?->getAttribute('name') ?? 'Store')),
                'logo_url' => filled($logoPath) ? asset('storage/'.$logoPath) : null,
                'phone' => $tenant instanceof Model ? $tenant->getAttribute('contact_phone') : null,
                'email' => $tenant instanceof Model ? $tenant->getAttribute('contact_email') : null,
                'address' => null,
                'website' => $primaryDomain instanceof Model && filled($primaryDomain->getAttribute('domain'))
                    ? 'https://'.$primaryDomain->getAttribute('domain')
                    : null,
            ],
            'order' => [
                'number' => (string) $this->order->getAttribute('order_number'),
                'invoice' => $this->order->getAttribute('invoice_number'),
                'date' => $placedAt instanceof CarbonInterface ? $placedAt->format('F j, Y g:i A') : '—',
                'status' => $status instanceof OrderStatus ? $status->label() : (string) $status,
                'customer_type' => $this->order->getAttribute('customer_id') === null ? 'Guest' : 'Registered customer',
            ],
            'customer' => [
                'name' => $this->customerValue('name', 'guest_name'),
                'phone' => $this->customerValue('phone', 'guest_phone'),
                'email' => $this->customerValue('email', 'guest_email'),
                'billing_address' => self::addressLines($this->order->getAttribute('billing_address_snapshot')),
                'shipping_address' => self::addressLines($this->order->getAttribute('shipping_address_snapshot')),
            ],
            'items' => $this->items($currency),
            'totals' => [
                'subtotal' => self::money((int) $this->order->getAttribute('subtotal'), $currency),
                'discount' => self::money((int) $this->order->getAttribute('discount_total'), $currency),
                'shipping' => self::money((int) $this->order->getAttribute('shipping_cost'), $currency),
                'tax' => self::money((int) $this->order->getAttribute('tax_total'), $currency),
                'grand_total' => self::money((int) $this->order->getAttribute('grand_total'), $currency),
                'amount_paid' => self::money($paid, $currency),
                'amount_due' => self::money((int) $this->order->getAttribute('grand_total') - $paid, $currency),
            ],
            'shipping' => [
                'method' => $shippingMethod instanceof Model ? $shippingMethod->getAttribute('name') : null,
            ],
            'payments' => $this->payments($payments, $currency),
            'footer' => $footer,
            'currency' => $currency,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $currency): array
    {
        $items = [];

        $orderItems = $this->order->getRelationValue('items');

        if (! $orderItems instanceof Collection) {
            return [];
        }

        foreach ($orderItems as $item) {
            $serials = [];
            $linkedSerials = $item->getRelationValue('serialNumbers');

            if ($linkedSerials instanceof Collection) {
                foreach ($linkedSerials as $serial) {
                    $value = $serial->getAttribute('imei_or_serial');

                    if (filled($value)) {
                        $serials[] = (string) $value;
                    }
                }
            }

            $items[] = [
                'product' => $item->getAttribute('product_name_snapshot'),
                'variant' => $item->getAttribute('variant_sku_snapshot'),
                'sku' => $item->getAttribute('variant_sku_snapshot'),
                'serials' => $serials,
                'quantity' => (int) $item->getAttribute('quantity'),
                'unit_price' => self::money((int) $item->getAttribute('unit_price'), $currency),
                // No per-line discount snapshot exists; do not infer one from catalog data.
                'discount' => null,
                'line_total' => self::money((int) $item->getAttribute('line_total'), $currency),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function addressLines(mixed $snapshot): array
    {
        if (! is_array($snapshot)) {
            return [];
        }

        $lines = [];

        foreach ([
            'recipient_name',
            'phone',
            'address_line_1',
            'address_line_2',
            'area',
            'city',
            'postal_code',
            'country',
        ] as $field) {
            $value = $snapshot[$field] ?? null;

            if (filled($value)) {
                $lines[] = ['label' => str($field)->replace('_', ' ')->title()->toString(), 'value' => (string) $value];
            }
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payments(mixed $payments, string $currency): array
    {
        if (! $payments instanceof Collection) {
            return [];
        }

        $presented = [];

        foreach ($payments as $payment) {
            $paymentMethod = $payment->getRelationValue('paymentMethod');
            $paymentStatus = $payment->getAttribute('status');
            $paidAt = $payment->getAttribute('paid_at');
            $createdAt = $payment->getAttribute('created_at');
            $paymentDate = $paidAt instanceof CarbonInterface ? $paidAt : $createdAt;

            $presented[] = [
                'date' => $paymentDate instanceof CarbonInterface ? $paymentDate->format('F j, Y g:i A') : '—',
                'method' => $paymentMethod instanceof Model ? $paymentMethod->getAttribute('name') : '—',
                'provider' => $paymentMethod instanceof Model ? $paymentMethod->getAttribute('gateway_driver') : null,
                'reference' => $payment->getAttribute('transaction_reference'),
                'amount' => self::money((int) $payment->getAttribute('amount'), $currency),
                'status' => $paymentStatus instanceof OrderPaymentStatus ? $paymentStatus->label() : (string) $paymentStatus,
            ];
        }

        return $presented;
    }

    private function paidAmount(mixed $payments): int
    {
        if (! $payments instanceof Collection) {
            return 0;
        }

        $paid = 0;

        foreach ($payments as $payment) {
            if ($payment->getAttribute('status') === OrderPaymentStatus::Paid) {
                $paid += (int) $payment->getAttribute('amount');
            }
        }

        return $paid;
    }

    private function customerValue(string $customerField, string $guestField): mixed
    {
        $customer = $this->order->getRelationValue('customer');

        if ($customer instanceof Model && filled($customer->getAttribute($customerField))) {
            return $customer->getAttribute($customerField);
        }

        return $this->order->getAttribute($guestField);
    }

    private static function money(int $minor, string $currency): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.currency_symbol($currency).number_format(abs($minor) / 100, 2, '.', ',');
    }
}
