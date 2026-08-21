@extends('storefront.account.layout')

@php
    use App\Enums\OrderEventType;
    use App\Enums\OrderStatus;

    $amountDue = max(0, $order->grand_total - $amountPaid);
    $isPaid = $order->grand_total > 0 && $amountPaid >= $order->grand_total;
    $paymentLabel = $isPaid ? 'Paid' : ($order->payments->isEmpty() ? 'Unpaid' : 'Partial');
    $paymentTone = $isPaid ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400'
        : ($order->payments->isEmpty() ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
            : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400');

    $orderTone = match ($order->status) {
        OrderStatus::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
        OrderStatus::Delivered => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
        OrderStatus::Cancelled => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    };

    $latestFulfillment = $order->fulfillments->first();
    $fulfillTone = match ($latestFulfillment?->status) {
        \App\Enums\OrderFulfillmentStatus::Delivered => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
        \App\Enums\OrderFulfillmentStatus::Failed => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
        default => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
    };

    $shipping = $order->shipping_address_snapshot ?? [];
    $billing = $order->billing_address_snapshot ?? [];

    $safeEvents = $order->events->whereIn('type', [
        OrderEventType::StatusChanged,
        OrderEventType::PaymentRecorded,
        OrderEventType::FulfillmentUpdated,
    ])->values()->reverse();
@endphp

@section('title', 'Order ' . $order->order_number . ' - ' . tenant()->name)

@section('account-content')
    {{-- Order header --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Order placed</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">{{ $order->order_number }}</h1>
                <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $order->placed_at?->format('F j, Y g:i A') ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $order->placed_at?->diffForHumans() }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $orderTone }}">{{ $order->status->label() }}</span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $paymentTone }}">Payment: {{ $paymentLabel }}</span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $fulfillTone }}">Shipping: {{ $latestFulfillment?->status?->label() ?? '—' }}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 gap-6 lg:grid lg:grid-cols-3 lg:items-start">
        {{-- Primary column --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Order items --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Items ({{ $order->items->count() }})</h2>
                </div>

                <div class="divide-y divide-gray-100 px-6 dark:divide-gray-800">
                    @foreach ($order->items as $item)
                        @php($image = $item->variant?->getFirstMediaUrl('images', 'thumb'))
                        <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center">
                            <div class="flex items-center gap-4">
                                @if ($image)
                                    <img src="{{ $image }}" alt="" class="h-16 w-16 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-gray-800">
                                @else
                                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="break-words font-medium text-gray-900 dark:text-white">
                                        {{ $item->product_name_snapshot }}
                                        @if (($item->fulfillment_strategy ?? null) === 'preorder')
                                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-xs">PRE-ORDER</span>
                                        @endif
                                    </p>
                                    @if ($item->variant_sku_snapshot)
                                        <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">SKU: {{ $item->variant_sku_snapshot }}</p>
                                    @endif
                                    @if (($item->fulfillment_strategy ?? null) === 'preorder' && $item->expected_available_at)
                                        <p class="mt-0.5 text-xs text-purple-600 dark:text-purple-400">Expected {{ $item->expected_available_at->format('M j, Y') }}</p>
                                    @endif
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 sm:hidden">
                                        {{ $item->quantity }} × ৳{{ number_format($item->unit_price / 100, 2) }}
                                    </p>
                                </div>
                            </div>

                            <div class="ml-20 flex items-center justify-between gap-4 sm:ml-auto sm:flex-none sm:text-right">
                                <span class="text-sm text-gray-500 dark:text-gray-400 sm:hidden">Total</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">৳{{ number_format($item->line_total / 100, 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Timeline --}}
            @if ($safeEvents->isNotEmpty())
                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order timeline</h2>
                    <ol class="mt-4">
                        @foreach ($safeEvents as $event)
                            @php($isLast = $loop->last)
                            <li class="flex gap-4 {{ $isLast ? '' : 'pb-6' }}">
                                <div class="flex flex-col items-center">
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-[var(--brand)]"></span>
                                    @if (! $isLast)
                                        <span class="mt-1 w-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $event->type?->label() }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $event->created_at?->format('M j, Y g:i A') }}</span>
                                    </div>
                                    @if ($event->from_status && $event->to_status)
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                            <span class="capitalize">{{ str($event->from_status)->replace('_', ' ') }}</span>
                                            <span class="mx-1">→</span>
                                            <span class="capitalize">{{ str($event->to_status)->replace('_', ' ') }}</span>
                                        </p>
                                    @elseif ($event->description)
                                        <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-300">{{ $event->description }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>

        {{-- Secondary column --}}
        <div class="mt-6 space-y-6 lg:mt-0">
            {{-- Summary --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">৳{{ number_format($order->subtotal / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Discount</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">−৳{{ number_format($order->discount_total / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Shipping</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">৳{{ number_format($order->shipping_cost / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Tax</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">৳{{ number_format($order->tax_total / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-800">
                        <dt class="text-base font-semibold text-gray-900 dark:text-white">Grand total</dt>
                        <dd class="text-base font-bold text-gray-900 dark:text-white">৳{{ number_format($order->grand_total / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Amount paid</dt>
                        <dd class="font-medium text-green-600 dark:text-green-400">৳{{ number_format($amountPaid / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Amount due</dt>
                        <dd class="font-medium {{ $amountDue > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">৳{{ number_format($amountDue / 100, 2) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Shipping --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Shipping address</h2>
                @if ($shipping)
                    @if ($order->shippingMethod)
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Method: {{ $order->shippingMethod->name }}</p>
                    @endif
                    <address class="mt-3 space-y-1 break-words text-sm not-italic text-gray-900 dark:text-white">
                        @if (! empty($shipping['recipient_name']))<p class="font-medium">{{ $shipping['recipient_name'] }}</p>@endif
                        @if (! empty($shipping['phone']))<p class="text-gray-600 dark:text-gray-300">{{ $shipping['phone'] }}</p>@endif
                        @if (! empty($shipping['address_line_1']))<p>{{ $shipping['address_line_1'] }}</p>@endif
                        @if (! empty($shipping['address_line_2']))<p>{{ $shipping['address_line_2'] }}</p>@endif
                        @if (! empty($shipping['area']))<p>{{ $shipping['area'] }}</p>@endif
                        @php($cityLine = trim(collect([$shipping['city'] ?? '', $shipping['postal_code'] ?? ''])->filter()->implode(', ')))
                        @if ($cityLine)<p>{{ $cityLine }}</p>@endif
                        @if (! empty($shipping['country']))<p>{{ $shipping['country'] }}</p>@endif
                    </address>
                @else
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No shipping address recorded.</p>
                @endif
            </div>

            {{-- Billing --}}
            @if ($billing)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Billing address</h2>
                    <address class="mt-3 space-y-1 break-words text-sm not-italic text-gray-900 dark:text-white">
                        @if (! empty($billing['recipient_name']))<p class="font-medium">{{ $billing['recipient_name'] }}</p>@endif
                        @if (! empty($billing['phone']))<p class="text-gray-600 dark:text-gray-300">{{ $billing['phone'] }}</p>@endif
                        @if (! empty($billing['address_line_1']))<p>{{ $billing['address_line_1'] }}</p>@endif
                        @if (! empty($billing['address_line_2']))<p>{{ $billing['address_line_2'] }}</p>@endif
                        @if (! empty($billing['area']))<p>{{ $billing['area'] }}</p>@endif
                        @php($billingCityLine = trim(collect([$billing['city'] ?? '', $billing['postal_code'] ?? ''])->filter()->implode(', ')))
                        @if ($billingCityLine)<p>{{ $billingCityLine }}</p>@endif
                        @if (! empty($billing['country']))<p>{{ $billing['country'] }}</p>@endif
                    </address>
                </div>
            @endif

            {{-- Payments --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payments</h2>
                @forelse ($order->payments as $payment)
                    <div class="mt-4 flex items-baseline justify-between gap-4 border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="break-words font-medium text-gray-900 dark:text-white">{{ $payment->paymentMethod?->name ?? 'Payment' }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $payment->paid_at?->format('M j, Y g:i A') ?? '—' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold text-gray-900 dark:text-white">৳{{ number_format($payment->amount / 100, 2) }}</p>
                            <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $payment->status->label() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No payments recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection